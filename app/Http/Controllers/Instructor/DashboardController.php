<?php

declare(strict_types=1);

namespace App\Http\Controllers\Instructor;

use App\Core\Access\Scope;
use App\Models\User;
use App\Modules\Commerce\Actions\CreatePayout;
use App\Modules\Commerce\Models\InstructorEarning;
use App\Modules\Community\Models\Discussion;
use App\Modules\Lms\Models\AssignmentSubmission;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseReview;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Instructor;
use App\Modules\Lms\Models\QuizAttempt;
use App\Modules\Services\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * لوحة المدرّس.
 *
 * كان المدرّس يُساق إلى لوحة صاحب المنصّة فيرى باقتها وحدود
 * استهلاكها — أرقام لا تخصّه ولا يملك عنها قراراً. هذه لوحته هو:
 * كورساته، وطلابه، وما ينتظر تصحيحه، وما استحقّه.
 */
final class DashboardController
{
    public function __construct(
        private readonly Scope $scope,
        private readonly CreatePayout $payouts,
    ) {}

    public function __invoke(): View
    {
        $user = $this->user();
        $instructorId = $this->scope->instructorIdFor($user);
        $courseIds = $this->scope->courseIdsFor($user);

        $enrollments = Enrollment::whereIn('course_id', $courseIds ?: [0]);

        return view('instructor.dashboard', [
            'courses' => Course::whereIn('id', $courseIds ?: [0])
                ->withCount('enrollments')
                ->latest('id')->limit(5)->get(),
            'coursesCount' => count($courseIds),
            'publishedCount' => Course::whereIn('id', $courseIds ?: [0])
                ->where('status', 'published')->count(),
            'studentsCount' => (clone $enrollments)->distinct()->count('user_id'),
            'activeCount' => (clone $enrollments)->where('status', 'active')->count(),
            'completedCount' => (clone $enrollments)->where('status', 'completed')->count(),

            // ما ينتظر عمله هو — لا إحصاء عامّ بلا فعل يتبعه
            'pendingAttempts' => $this->scope->byCourseVia(
                QuizAttempt::query()->where('status', 'submitted')
                    ->whereHas('answers', fn ($q) => $q->whereNull('is_correct')),
                $user, 'enrollment',
            )->count(),
            'pendingSubmissions' => $this->scope->byCourseVia(
                AssignmentSubmission::query()->where('status', 'pending'),
                $user, 'enrollment',
            )->count(),
            'openQuestions' => Discussion::whereIn('course_id', $courseIds ?: [0])
                ->unanswered()->count(),
            'pendingBookings' => $this->pendingBookings($user),

            'rating' => $this->rating($courseIds),
            'reviewsCount' => CourseReview::whereIn('course_id', $courseIds ?: [0])->approved()->count(),

            'balance' => $instructorId === null ? null : $this->payouts->balanceFor(
                Instructor::find($instructorId),
            ),
            'lifetime' => $this->lifetime($instructorId),

            'latest' => $enrollments->with(['user', 'course'])->latest('id')->limit(8)->get(),
        ]);
    }

    /** متوسّط التقييم على كورساته — لا تقييم يعني لا شيء لا صفراً. */
    private function rating(array $courseIds): ?float
    {
        if ($courseIds === []) {
            return null;
        }

        $average = CourseReview::whereIn('course_id', $courseIds)->approved()->avg('rating');

        return $average === null ? null : round((float) $average, 1);
    }

    private function lifetime(?int $instructorId): int
    {
        return $instructorId === null ? 0 : (int) InstructorEarning::where('instructor_id', $instructorId)
            ->whereIn('status', ['available', 'paid'])
            ->sum('amount_minor');
    }

    private function pendingBookings(?User $user): int
    {
        if (! DB::getSchemaBuilder()->hasTable('bookings')) {
            return 0;
        }

        return Booking::query()
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->whereHas('provider', fn ($q) => $q->where('user_id', $user?->getKey()))
            ->count();
    }

    private function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
