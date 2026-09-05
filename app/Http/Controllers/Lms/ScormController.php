<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Ability;
use App\Modules\Lms\LessonAccess;
use App\Modules\Lms\Models\Lesson;
use App\Modules\Lms\Models\ScormPackage;
use App\Modules\Lms\Scorm\ScormPackager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use RuntimeException;

/**
 * حزم SCORM — رفعها وتتبّع تقدّم الطلبة فيها.
 */
final class ScormController
{
    public function index(Request $request, int $lessonId): View
    {
        $this->authorise($request);

        $lesson = Lesson::findOrFail($lessonId);

        return view('admin.scorm', [
            'lesson' => $lesson,
            'package' => ScormPackage::where('lesson_id', $lesson->getKey())
                ->with(['states.user'])->first(),
        ]);
    }

    public function store(Request $request, int $lessonId, ScormPackager $packager): RedirectResponse
    {
        $this->authorise($request);

        $lesson = Lesson::findOrFail($lessonId);

        $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:512000'],
        ]);

        try {
            $extracted = $packager->extract($request->file('package'), 'public');
        } catch (RuntimeException $e) {
            return back()->withErrors(['package' => $e->getMessage()]);
        }

        /*
         | حزمةٌ واحدة لكل درس.
         |
         | الرفع الثاني يستبدل الأول ويحذف ملفّاته: حزمتان في درسٍ
         | واحد لا معنى لهما، وترك القديمة يملأ القرص بما لا يُفتح.
         |
         | والحالات تبقى: طالبٌ أتمّ النسخة القديمة لا يُمحى تقدّمه
         | لأن مدرّسه صحّح مطبعةً في الشرائح.
         */
        $existing = ScormPackage::where('lesson_id', $lesson->getKey())->first();

        if ($existing !== null) {
            File::deleteDirectory(storage_path('app/public/'.$existing->path));
            $existing->forceFill($extracted)->save();
        } else {
            ScormPackage::create(['lesson_id' => $lesson->getKey(), ...$extracted]);
        }

        $lesson->forceFill(['type' => 'scorm'])->save();

        return back()->with('status', __('رُفعت الحزمة (SCORM :v).', ['v' => $extracted['version']]));
    }

    public function destroy(Request $request, int $lessonId): RedirectResponse
    {
        $this->authorise($request);

        $package = ScormPackage::where('lesson_id', $lessonId)->firstOrFail();

        File::deleteDirectory(storage_path('app/public/'.$package->path));
        $package->delete();

        return back()->with('status', __('حُذفت الحزمة.'));
    }

    /**
     * حفظ حالة الطالب — يُنادى من جسر SCORM.
     *
     * والتسجيل يُفحص هنا وإن كان الدرس محروساً: النقطة تُنادى
     * مباشرةً بمعرّفٍ في المسار، ومن مرّ عليها بلا تسجيل يكتب
     * درجاتٍ في حزمةٍ لا يملكها — فيظهر في تقرير مدرّسٍ لا يعرفه.
     */
    public function state(Request $request, int $packageId, LessonAccess $access): JsonResponse
    {
        $package = ScormPackage::with('lesson')->findOrFail($packageId);
        $user = $request->user();

        abort_if($user === null, 403);

        abort_unless($access->grants($user, $package->lesson), 403);

        $state = $package->stateFor($user);

        $input = $request->validate([
            'cmi' => ['nullable', 'array'],
            'lesson_status' => ['nullable', 'string', 'max:24'],
            'score_raw' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'suspend_data' => ['nullable', 'string', 'max:65000'],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        $state->forceFill([
            'cmi' => $input['cmi'] ?? $state->cmi,
            'lesson_status' => $input['lesson_status'] ?: $state->lesson_status,
            'score_raw' => $input['score_raw'] ?? $state->score_raw,
            'location' => $input['location'] ?? $state->location,
            'suspend_data' => $input['suspend_data'] ?? $state->suspend_data,
            // الزمن يُجمَّع لا يُستبدَل: الجلسة الثانية تُضاف إلى الأولى
            'total_seconds' => (int) $state->total_seconds + (int) ($input['seconds'] ?? 0),
        ])->save();

        return response()->json(['saved' => true]);
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::LESSONS_MANAGE), 403);
    }
}
