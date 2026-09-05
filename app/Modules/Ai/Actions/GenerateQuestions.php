<?php

declare(strict_types=1);

namespace App\Modules\Ai\Actions;

use App\Modules\Ai\AiClient;
use App\Modules\Lms\Models\Question;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * يولّد أسئلةً من نصّ يُلصق أو ملفٍّ يُرفع.
 *
 * المدرّس يكتب امتحان الوحدة في ساعة، ويكرّرها كل وحدة. وهذا يختصر
 * الساعة إلى مراجعةِ خمس دقائق — والمراجعة لا تُلغى.
 *
 * ## ولماذا لا تدخل امتحاناً مباشرةً
 *
 * النموذج يُخطئ في التفاصيل: يخلط سنةً، أو يضع إجابتين صحيحتين، أو
 * يسأل عمّا ليس في المنهج. وامتحانٌ فيه سؤالٌ خاطئ يُفقد المدرّس
 * ثقة طلابه — وهي أغلى من الساعة التي وفّرها.
 *
 * فتدخل البنك وحده، ولا تدخل امتحاناً إلا بيد المدرّس — وهو يقرؤها
 * وهو يختارها.
 */
final class GenerateQuestions
{
    private const SYSTEM = <<<'TXT'
        أنت مساعد مدرّس عربي تُعدّ أسئلة امتحان من مادة يعطيك إياها.

        القواعد:
        - اكتب بالعربية الفصيحة الواضحة، بمستوى الطلاب لا بمستواك.
        - لا تسأل إلا عمّا ورد في المادة نصّاً. ولا تستنتج من خارجها.
        - لكل سؤال إجابة صحيحة واحدة لا تحتمل غيرها.
        - البدائل الخاطئة معقولة لا سخيفة: بديلٌ واضح البطلان لا يقيس شيئاً.
        - التزم عدد الأسئلة والمستوى المطلوبين بالضبط.

        أجب بـJSON بهذا الشكل وحده:
        {"questions":[{"body":"نصّ السؤال","type":"single_choice","options":["أ","ب","ج","د"],"correct":"أ","explanation":"لماذا","difficulty":"easy"}]}

        القيم المسموحة: type = single_choice | true_false | short_text
        difficulty = easy | medium | hard
        TXT;

    public function __construct(private readonly AiClient $client) {}

    /**
     * @return array{created:int, skipped:int}
     *
     * @throws RuntimeException
     */
    public function handle(string $material, int $count, string $difficulty, ?int $poolId = null): array
    {
        $material = trim($material);

        if (mb_strlen($material) < 120) {
            throw new RuntimeException(__('المادة قصيرة جداً — الصق نصّاً أطول أو ارفع ملفاً.'));
        }

        $count = max(1, min(30, $count));

        $result = $this->client->askJson(self::SYSTEM, __(
            "المادة:\n\n:material\n\nأنشئ :count سؤالاً بمستوى :difficulty.",
            ['material' => $material, 'count' => $count, 'difficulty' => $difficulty],
        ));

        $questions = $result['questions'] ?? [];

        if (! is_array($questions) || $questions === []) {
            throw new RuntimeException(__('لم يُنتج النموذج أسئلة. جرّب مادة أوضح.'));
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($questions, $poolId, $difficulty, &$created, &$skipped): void {
            foreach ($questions as $row) {
                if (! $this->valid($row)) {
                    $skipped++;

                    continue;
                }

                Question::create([
                    'category_id' => $poolId,
                    'type' => $row['type'],
                    // العنوان يُشتقّ من أول السؤال: قائمة البنك تعرضه لا نصّه كاملاً
                    'title' => mb_substr(strip_tags((string) $row['body']), 0, 80),
                    'body' => ['ar' => trim((string) $row['body'])],
                    'options' => $row['type'] === 'single_choice'
                        ? array_values((array) $row['options'])
                        : null,
                    'correct' => [(string) $row['correct']],
                    'explanation' => filled($row['explanation'] ?? null)
                        ? ['ar' => (string) $row['explanation']]
                        : null,
                    'difficulty' => in_array($row['difficulty'] ?? '', ['easy', 'medium', 'hard'], true)
                        ? $row['difficulty']
                        : $difficulty,
                    'marks' => 1,
                ]);

                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * ما لا يصلح سؤالاً يُتخطّى ولا يُوقف الباقي.
     *
     * جوابٌ فيه عشرون سؤالاً وواحدٌ ناقص يُنتج تسعة عشر — ورفضُ
     * العشرين لأجل واحدٍ يُضيّع عملاً صحيحاً.
     *
     * @param  mixed  $row
     */
    private function valid($row): bool
    {
        if (! is_array($row) || blank($row['body'] ?? null) || blank($row['correct'] ?? null)) {
            return false;
        }

        if (! in_array($row['type'] ?? '', ['single_choice', 'true_false', 'short_text'], true)) {
            return false;
        }

        if ($row['type'] !== 'single_choice') {
            return true;
        }

        $options = $row['options'] ?? null;

        // والإجابة الصحيحة يجب أن تكون من البدائل فعلاً
        return is_array($options)
            && count($options) >= 2
            && in_array((string) $row['correct'], array_map('strval', $options), true);
    }
}
