<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Scope;
use App\Core\Entitlements\Exceptions\QuotaExceededException;
use App\Models\User;
use App\Modules\Ai\Actions\BuildCourseOutline;
use App\Modules\Lms\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * بناء هيكل منهجٍ بالذكاء الاصطناعي.
 *
 * الأقسام والدروس تُنشأ مسوّدةً، والمدرّس يملؤها ويحذف ما لا يريد.
 */
final class AiOutlineController
{
    public function __construct(private readonly Scope $scope) {}

    public function index(Request $request, string $courseId): View
    {
        $course = $this->courseFor($request, $courseId);

        return view('admin.ai-outline', ['course' => $course]);
    }

    public function store(Request $request, string $courseId, BuildCourseOutline $action): RedirectResponse
    {
        $course = $this->courseFor($request, $courseId);

        $input = $request->validate([
            'brief' => ['required', 'string', 'min:20', 'max:4000'],
            'sections' => ['required', 'integer', 'min:1', 'max:12'],
            'per_section' => ['required', 'integer', 'min:1', 'max:10'],
            'level' => ['required', 'string', 'max:60'],
        ]);

        try {
            $made = $action->handle(
                $course,
                (string) $input['brief'],
                (int) $input['sections'],
                (int) $input['per_section'],
                (string) $input['level'],
            );
        } catch (QuotaExceededException $e) {
            return back()->withInput()->withErrors(['brief' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['brief' => $e->getMessage()]);
        }

        /*
         | ويُنقَل إلى المنهج لا يبقى في شاشة التوليد.
         |
         | ما بُني يُراجَع، ومن يبقى في الشاشة نفسها لا يرى ما بناه
         | فيولّد مرّةً ثانية فوق الأولى.
         */
        return redirect(url('/admin/courses/'.$course->getKey().'/curriculum'))
            ->with('status', __('أُضيف :sections أقسام و:lessons دروس — راجعها واحذف ما لا يناسبك.', [
                'sections' => $made['sections'],
                'lessons' => $made['lessons'],
            ]));
    }

    /** الكورس إن كان كورس صاحب الطلب — وإلا 404. */
    private function courseFor(Request $request, string $courseId): Course
    {
        $user = $request->user();

        return $this->scope
            ->byInstructor(Course::query(), $user instanceof User ? $user : null, 'instructor_id')
            ->findOrFail($courseId);
    }
}
