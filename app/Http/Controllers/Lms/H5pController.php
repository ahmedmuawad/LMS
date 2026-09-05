<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Ability;
use App\Modules\Lms\Actions\RecordXapiStatement;
use App\Modules\Lms\H5p\H5pPackager;
use App\Modules\Lms\LessonAccess;
use App\Modules\Lms\Models\H5pPackage;
use App\Modules\Lms\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use RuntimeException;

/**
 * حزم H5P — رفعها وتشغيلها وقراءة نتائجها.
 */
final class H5pController
{
    public function index(Request $request, int $lessonId): View
    {
        $this->authorise($request);

        $lesson = Lesson::findOrFail($lessonId);
        $package = H5pPackage::where('lesson_id', $lesson->getKey())->first();

        return view('admin.h5p', [
            'lesson' => $lesson,
            'package' => $package,
            'results' => $package?->results() ?? collect(),
        ]);
    }

    public function store(Request $request, int $lessonId, H5pPackager $packager): RedirectResponse
    {
        $this->authorise($request);

        $lesson = Lesson::findOrFail($lessonId);

        /*
         | الامتداد `.h5p` لا `.zip`.
         |
         | و`mimes` تفحص الامتداد بجدول لارافيل ولا تعرف هذا، فيُفحص
         | بـ`extensions` ويُترك فحصُ المحتوى للفكّ نفسه: ما ليس ZIP
         | لا يُفتح، وما ليس H5P لا يجد `h5p.json`.
         */
        $request->validate([
            'package' => ['required', 'file', 'extensions:h5p,zip', 'max:512000'],
        ], [], ['package' => __('الحزمة')]);

        try {
            $extracted = $packager->extract($request->file('package'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['package' => $e->getMessage()]);
        }

        /*
         | حزمةٌ واحدة لكل درس، والرفع الثاني يستبدل الأول ويحذف
         | ملفّاته. والنتائج تبقى: عبارات xAPI مفصولة عن الحزمة
         | فلا يُمحى تقدّم طالبٍ لأن مدرّسه صحّح مطبعةً في الشرائح.
         */
        $existing = H5pPackage::where('lesson_id', $lesson->getKey())->first();

        if ($existing !== null) {
            File::deleteDirectory(storage_path('app/public/'.$existing->path));
            $existing->forceFill($extracted)->save();
        } else {
            H5pPackage::create(['lesson_id' => $lesson->getKey(), ...$extracted]);
        }

        $lesson->forceFill(['type' => 'h5p'])->save();

        return back()->with('status', __('رُفعت الحزمة التفاعلية.'));
    }

    public function destroy(Request $request, int $lessonId): RedirectResponse
    {
        $this->authorise($request);

        $package = H5pPackage::where('lesson_id', $lessonId)->firstOrFail();

        File::deleteDirectory(storage_path('app/public/'.$package->path));
        $package->delete();

        return back()->with('status', __('حُذفت الحزمة.'));
    }

    /**
     * نتيجةٌ يُبلّغ بها المشغّل داخل المنصة.
     *
     * ## والفاعل من الجلسة لا من العبارة
     *
     * العبارة تصل من متصفّح الطالب، وفيها اسم فاعلها كما يكتبه
     * المشغّل. ولو صُدِّق ما فيها لكتب طالبٌ درجةً كاملة باسم زميله
     * بتحرير طلبٍ واحد. فالاسم فيها يُتجاهَل، ويُكتب صاحب الجلسة.
     *
     * ## والهدف يُستبدَل كذلك
     *
     * المشغّل يضع هدفاً من عنده (مسار المحتوى)، ويتكرّر بين الحزم.
     * فيُوضع مكانه معرّفنا `h5p:{id}` — وإلا اختلطت نتائج حزمتين
     * أُلّفتا في نفس الأداة.
     */
    public function xapi(Request $request, int $packageId, LessonAccess $access, RecordXapiStatement $record): JsonResponse
    {
        $package = H5pPackage::with('lesson')->findOrFail($packageId);
        $user = $request->user();

        abort_unless($access->grants($user, $package->lesson), 403);

        $statement = $request->isJson() ? (array) $request->json()->all() : $request->all();

        abort_unless(is_array($statement) && filled($statement['verb']['id'] ?? null), 422);

        $statement['object'] = [
            'id' => $this->objectId($package, $statement),
            'definition' => [
                'name' => ['ar' => $package->title ?: $package->lesson?->title],
                'type' => $statement['object']['definition']['type'] ?? null,
            ],
        ];

        $id = $record->handle($statement, $user);

        return response()->json(['id' => $id]);
    }

    /**
     * هدفٌ للحزمة، وهدفٌ فرعيّ لكل سؤالٍ داخلها.
     *
     * التقرير يعرض نتيجة الحزمة، ومن أراد التفصيل نزل إلى أسئلتها.
     * ولو حملت كلّها معرّفاً واحداً لصار الطالب عشرة صفوفٍ لا يُعرف
     * أيّها نتيجته.
     *
     * @param  array<mixed>  $statement
     */
    private function objectId(H5pPackage $package, array $statement): string
    {
        $sub = $statement['context']['contextActivities']['parent'][0]['id'] ?? null;

        if ($sub === null) {
            return $package->objectId();
        }

        // السؤال الداخلي: يُختصر أثرُه إلى بصمةٍ قصيرة ثابتة
        $raw = (string) ($statement['object']['id'] ?? '');

        return $raw === ''
            ? $package->objectId()
            : $package->objectId().'/'.substr(sha1($raw), 0, 12);
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::LESSONS_MANAGE), 403);
    }
}
