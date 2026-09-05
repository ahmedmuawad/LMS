<?php

declare(strict_types=1);

namespace App\Modules\Ai\Actions;

use App\Modules\Ai\AiClient;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\CourseSection;
use App\Modules\Lms\Models\Lesson;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * يبني هيكل منهجٍ لكورس: أقسامه ودروسه وعناوينها.
 *
 * ## ما يبنيه وما لا يبنيه
 *
 * يبني **الهيكل** لا **المحتوى**: أسماء الأقسام والدروس وترتيبها
 * ومدّتها المقدّرة ووصفاً سطرياً لكل درس. ولا يكتب شرحاً ولا يصنع
 * فيديو — فالمدرّس يملأ ما بناه.
 *
 * والسبب أن الصفحة البيضاء هي ما يُعطّل: المدرّس يعرف مادّته ويعرف
 * كيف يشرحها، ويقف عند «من أين أبدأ وكم قسماً». وهذا ما يُختصَر.
 *
 * ## والدروس تُنشأ مسوّدةً بلا محتوى
 *
 * كلّها من نوع «نص» بلا جسم: فنوعٌ آخر (فيديو، حزمة) يَعِد بملفٍّ
 * غير موجود، ويجعل الكورس يبدو جاهزاً وهو فارغ.
 */
final class BuildCourseOutline
{
    private const SYSTEM = <<<'TXT'
        أنت مصمّم مناهج عربي تضع هيكل كورس لمدرّس.

        القواعد:
        - اكتب بالعربية الفصيحة الواضحة.
        - رتّب من الأسهل إلى الأصعب، ولا تفترض معرفةً لم تُشرح قبلها.
        - عنوان الدرس جملةٌ تصف ما يتعلّمه الطالب، لا كلمةً مجرّدة.
        - لكل درس وصفٌ سطرٌ واحد: ماذا يعرف الطالب بعده.
        - المدّة تقديرٌ بالدقائق لدرسٍ مسجَّل، بين ٥ و٤٥.
        - التزم عدد الأقسام والدروس المطلوبين بالضبط.

        أجب بـJSON بهذا الشكل وحده:
        {"sections":[{"title":"اسم القسم","lessons":[{"title":"عنوان الدرس","summary":"ماذا يتعلّم","minutes":15}]}]}
        TXT;

    public function __construct(private readonly AiClient $client) {}

    /**
     * @return array{sections:int, lessons:int}
     *
     * @throws RuntimeException
     */
    public function handle(Course $course, string $brief, int $sections, int $perSection, string $level): array
    {
        $brief = trim($brief);

        if (mb_strlen($brief) < 20) {
            throw new RuntimeException(__('اكتب وصفاً أوضح للكورس — سطران يكفيان.'));
        }

        $sections = max(1, min(12, $sections));
        $perSection = max(1, min(10, $perSection));

        $result = $this->client->askJson(self::SYSTEM, __(
            "الكورس: :title\n\nوصفه: :brief\n\nمستوى الطلبة: :level\n\nأنشئ :sections أقسام، في كلٍّ منها :per دروس.",
            [
                'title' => (string) $course->title,
                'brief' => $brief,
                'level' => $level,
                'sections' => $sections,
                'per' => $perSection,
            ],
        ));

        $plan = $result['sections'] ?? [];

        if (! is_array($plan) || $plan === []) {
            throw new RuntimeException(__('لم يُنتج النموذج هيكلاً. جرّب وصفاً أوضح.'));
        }

        return $this->persist($course, $plan);
    }

    /**
     * @param  array<mixed>  $plan
     * @return array{sections:int, lessons:int}
     */
    private function persist(Course $course, array $plan): array
    {
        $made = ['sections' => 0, 'lessons' => 0];

        DB::transaction(function () use ($course, $plan, &$made): void {
            /*
             | يُضاف إلى ما هو موجود ولا يُستبدَل به.
             |
             | مدرّسٌ بنى قسمين ثم استعان بالمولّد لا يقبل أن يُمحى
             | عملُه — والإضافة يُصلحها الحذف، والمحو لا يُصلحه شيء.
             */
            $position = (int) $course->sections()->max('position');
            $itemPosition = (int) $course->items()->max('position');

            foreach ($plan as $row) {
                if (! is_array($row) || blank($row['title'] ?? null)) {
                    continue;
                }

                $section = CourseSection::create([
                    'course_id' => $course->getKey(),
                    'title' => ['ar' => mb_substr(strip_tags((string) $row['title']), 0, 180)],
                    'position' => ++$position,
                ]);

                $made['sections']++;

                foreach ((array) ($row['lessons'] ?? []) as $item) {
                    if (! is_array($item) || blank($item['title'] ?? null)) {
                        continue;
                    }

                    $minutes = (int) ($item['minutes'] ?? 0);
                    $minutes = $minutes >= 1 && $minutes <= 600 ? $minutes : 15;

                    $lesson = Lesson::create([
                        'title' => ['ar' => mb_substr(strip_tags((string) $item['title']), 0, 180)],
                        'type' => 'text',
                        'content' => ['ar' => trim(strip_tags((string) ($item['summary'] ?? '')))],
                        'duration_seconds' => $minutes * 60,
                    ]);

                    CourseItem::create([
                        'course_id' => $course->getKey(),
                        'section_id' => $section->getKey(),
                        'itemable_type' => Lesson::class,
                        'itemable_id' => $lesson->getKey(),
                        'position' => ++$itemPosition,
                    ]);

                    $made['lessons']++;
                }
            }
        });

        $this->refreshCounts($course);

        return $made;
    }

    private function refreshCounts(Course $course): void
    {
        $items = $course->items()->with('itemable')->get();

        $course->forceFill([
            'lessons_count' => $items->count(),
            'duration_minutes' => (int) round($items
                ->filter(fn (CourseItem $i): bool => $i->itemable instanceof Lesson)
                ->sum(fn (CourseItem $i): int => (int) $i->itemable->duration_seconds) / 60),
        ])->save();
    }
}
