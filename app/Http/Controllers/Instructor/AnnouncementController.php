<?php

declare(strict_types=1);

namespace App\Http\Controllers\Instructor;

use App\Core\Access\Scope;
use App\Models\User;
use App\Modules\Community\Models\Discussion;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * إعلانات المدرّس على كورساته.
 *
 * الإعلان مناقشة من نوع `announcement` لا جدول ثالث: هو يعيش في
 * صفحة الكورس مع الأسئلة، ويُقرأ حيث يقرأ الطالب لا في بريد يُهمَل.
 */
final class AnnouncementController
{
    public function __construct(private readonly Scope $scope) {}

    public function index(Request $request): View
    {
        $user = $this->user($request);

        return view('instructor.announcements', [
            'announcements' => $this->scope
                ->byCourse(Discussion::query(), $user)
                ->where('type', 'announcement')
                ->with(['course', 'user'])
                ->latest('id')->paginate(20),
            'courses' => $this->scope->byInstructor(Course::query(), $user)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $input = $request->validate([
            'course_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'notify' => ['nullable', 'boolean'],
        ]);

        // الكورس من كورساته هو — وإلا أعلن على طلاب غيره
        $course = $this->scope->byInstructor(Course::query(), $user)
            ->findOrFail($input['course_id']);

        $announcement = Discussion::create([
            'type' => 'announcement',
            'course_id' => $course->getKey(),
            'user_id' => $request->user()?->getKey(),
            'title' => $input['title'],
            'body' => $input['body'],
            'status' => 'open',
            'is_pinned' => true,
        ]);

        if ((bool) ($input['notify'] ?? false)) {
            $this->announce($course, $announcement);
        }

        return back()->with('status', __('نُشر الإعلان.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->scope->byCourse(Discussion::query(), $this->user($request))
            ->where('type', 'announcement')
            ->findOrFail($id)
            ->delete();

        return back()->with('status', __('حُذف الإعلان.'));
    }

    /**
     * الإرسال على دفعات: كورس بألف طالب يعني ألف إشعار، وتحميلها
     * في الذاكرة دفعة واحدة يُسقط الطلب قبل أن يصل أوّلها.
     */
    private function announce(Course $course, Discussion $announcement): void
    {
        Enrollment::where('course_id', $course->getKey())
            ->whereIn('status', ['active', 'completed'])
            ->with('user')
            ->chunkById(200, function ($rows) use ($course, $announcement): void {
                foreach ($rows as $enrollment) {
                    notify('lms.announcement', $enrollment->user, [
                        'course_title' => (string) ($course->title ?? ''),
                        'title' => $announcement->title,
                        'excerpt' => Str::limit($announcement->body, 160),
                        'url' => url('/learn/'.$course->slug),
                    ]);
                }
            });
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
