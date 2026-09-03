<?php

declare(strict_types=1);

namespace App\Modules\Community\Actions;

use App\Models\User;
use App\Modules\Community\Models\ServiceReview;
use App\Modules\Gamification\Actions\AwardPoints;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseReview;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Services\Models\Booking;
use App\Modules\Services\Models\Service;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * كتابة تقييم ومراجعته وإعادة حساب المتوسط.
 *
 * التقييم من غير مشترٍ لا قيمة له: يُفسد المتوسط ويفتح باب الحرب
 * بين المنافسين. لذا الأهلية تُفحص هنا لا في الواجهة.
 */
final class SubmitReview
{
    public function __construct(private readonly AwardPoints $points) {}

    /** @param  array<string, mixed>  $input */
    public function forCourse(User $user, Course $course, array $input): CourseReview
    {
        $this->assertReviewsAreOn();
        $this->assertMayReviewCourse($user, $course);

        $rating = $this->rating($input);

        $review = CourseReview::updateOrCreate(
            ['course_id' => $course->getKey(), 'user_id' => $user->getKey()],
            [
                'rating' => $rating,
                'body' => $input['body'] ?? null,
                'status' => $this->initialStatus($rating),
            ],
        );

        $this->recalculateCourse($course);
        $this->points->handle($user, 'review.written', $review);

        return $review;
    }

    /** @param  array<string, mixed>  $input */
    public function forService(User $user, Service $service, array $input): ServiceReview
    {
        $this->assertReviewsAreOn();

        $booking = Booking::where('service_id', $service->getKey())
            ->where('user_id', $user->getKey())
            ->whereIn('status', ['completed', 'delivered'])
            ->latest('id')
            ->first();

        if ($booking === null && (string) setting('community.who_can_review', 'purchased') !== 'enrolled') {
            throw new RuntimeException(__('التقييم لمن استفاد من الخدمة فعلاً.'));
        }

        $rating = $this->rating($input);

        $review = ServiceReview::updateOrCreate(
            ['service_id' => $service->getKey(), 'user_id' => $user->getKey()],
            [
                'booking_id' => $booking?->getKey(),
                'rating' => $rating,
                'body' => $input['body'] ?? null,
                'status' => $this->initialStatus($rating),
            ],
        );

        $this->recalculateService($service);
        $this->points->handle($user, 'review.written', $review);

        return $review;
    }

    public function moderate(Model $review, string $status, ?string $reply = null): Model
    {
        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new RuntimeException(__('حالة غير معروفة.'));
        }

        $review->forceFill([
            'status' => $status,
            'reply' => $reply ?? $review->reply,
            'replied_at' => $reply !== null ? now() : $review->replied_at,
        ])->save();

        $review instanceof CourseReview
            ? $this->recalculateCourse($review->course)
            : $this->recalculateService($review->service);

        return $review;
    }

    /** المتوسط يُعاد جمعه من المنشور وحده: المعلَّق لا يدخل الحساب. */
    public function recalculateCourse(?Course $course): void
    {
        if ($course === null) {
            return;
        }

        $approved = CourseReview::where('course_id', $course->getKey())->approved();

        $course->forceFill([
            'rating_avg' => round((float) $approved->clone()->avg('rating'), 2),
            'ratings_count' => $approved->clone()->count(),
        ])->save();
    }

    public function recalculateService(?Service $service): void
    {
        if ($service === null) {
            return;
        }

        $approved = ServiceReview::where('service_id', $service->getKey())->approved();

        $service->forceFill([
            'rating_avg' => round((float) $approved->clone()->avg('rating'), 2),
        ])->save();
    }

    /** @param  array<string, mixed>  $input */
    private function rating(array $input): int
    {
        $rating = (int) ($input['rating'] ?? 0);

        if ($rating < 1 || $rating > 5) {
            throw new RuntimeException(__('التقييم من نجمة إلى خمس.'));
        }

        return $rating;
    }

    private function initialStatus(int $rating): string
    {
        if (! (bool) setting('community.moderate_reviews', true)) {
            return 'approved';
        }

        // نشر العالي تلقائياً يقلّل العبء ويُبقي المراجعة على السلبي
        return $rating >= 4 && (bool) setting('community.auto_approve_high', false)
            ? 'approved'
            : 'pending';
    }

    private function assertReviewsAreOn(): void
    {
        if (! (bool) setting('community.reviews', true)) {
            throw new RuntimeException(__('التقييمات معطّلة في هذا الموقع.'));
        }
    }

    private function assertMayReviewCourse(User $user, Course $course): void
    {
        $enrollment = Enrollment::where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->first();

        if ($enrollment === null) {
            throw new RuntimeException(__('التقييم لمن سجّل في الكورس.'));
        }

        $policy = (string) setting('community.who_can_review', 'purchased');

        if ($policy === 'completed' && $enrollment->status !== 'completed') {
            throw new RuntimeException(__('التقييم بعد إتمام الكورس.'));
        }

        if ($policy === 'purchased' && $enrollment->source === 'manual' && $enrollment->order_item_id === null) {
            throw new RuntimeException(__('التقييم لمن اشترى الكورس.'));
        }
    }
}
