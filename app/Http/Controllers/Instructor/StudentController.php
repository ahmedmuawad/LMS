<?php

declare(strict_types=1);

namespace App\Http\Controllers\Instructor;

use App\Core\Access\Scope;
use App\Models\User;
use App\Modules\Lms\Models\AssignmentSubmission;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\LessonProgress;
use App\Modules\Lms\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * طلاب المدرّس وتقدّم كل واحد منهم.
 *
 * الطالب هنا **تسجيل** لا حساب: المدرّس يرى من دخل كورساته وحالته
 * فيها، لا دفتر مستخدمي المنصّة. ومن ثمّ لا بريد ولا هاتف ولا طلبات
 * شراء — تلك بيانات صاحب المنصّة لا بيانات كورس.
 */
final class StudentController
{
    public function __construct(private readonly Scope $scope) {}

    public function index(Request $request): View
    {
        $user = $this->user($request);
        $courseIds = $this->courseIds($user);

        $query = Enrollment::query()
            ->when($courseIds !== null, fn ($q) => $q->whereIn('course_id', $courseIds ?: [0]))
            ->with(['user', 'course']);

        if (filled($search = trim((string) $request->input('q')))) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));
        }

        if (filled($courseId = $request->input('course'))) {
            $query->where('course_id', (int) $courseId);
        }

        if (filled($status = $request->input('status')) && isset(Enrollment::STATUSES[$status])) {
            $query->where('status', $status);
        }

        return view('instructor.students', [
            'enrollments' => $query->latest('id')->paginate(25)->withQueryString(),
            'courses' => Course::query()
                ->when($courseIds !== null, fn ($q) => $q->whereIn('id', $courseIds ?: [0]))
                ->get(),
            'search' => $search,
            'course' => $courseId,
            'status' => $status,
        ]);
    }

    /** ملفّ التقدّم: أين وصل، وما درجاته، وما ينتظر تصحيحه. */
    public function show(Request $request, string $id): View
    {
        $user = $this->user($request);
        $courseIds = $this->courseIds($user);

        $enrollment = Enrollment::query()
            ->when($courseIds !== null, fn ($q) => $q->whereIn('course_id', $courseIds ?: [0]))
            ->with(['user', 'course.items.itemable'])
            ->findOrFail($id);

        return view('instructor.student', [
            'enrollment' => $enrollment,
            'progress' => LessonProgress::where('enrollment_id', $enrollment->getKey())
                ->with('item.itemable')
                ->get()
                ->keyBy('item_id'),
            'attempts' => QuizAttempt::where('enrollment_id', $enrollment->getKey())
                ->with('quiz')->latest('id')->get(),
            'submissions' => AssignmentSubmission::where('enrollment_id', $enrollment->getKey())
                ->with('assignment')->latest('id')->get(),
        ]);
    }

    /**
     * قائمة الكورسات التي تحدّ الرؤية، و`null` لمن لا يُحصر نطاقه.
     *
     * التمييز مقصود: `[]` تعني «لا كورسات فلا طلاب»، و`null` تعني
     * «لا حصر» — وخلطهما يُري المالك لا شيء أو يُري المدرّس كل شيء.
     *
     * @return list<int>|null
     */
    private function courseIds(?User $user): ?array
    {
        return $user !== null && $user->isScoped() ? $this->scope->courseIdsFor($user) : null;
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
