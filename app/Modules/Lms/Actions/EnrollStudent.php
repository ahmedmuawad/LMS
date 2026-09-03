<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Models\User;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Enrollment;
use RuntimeException;

/**
 * تسجيل طالب في كورس.
 *
 * التسجيل مستقل عن الشراء عمداً: يأتي من هدية أو منحة أو استيراد
 * أو كود شحن. ولا يتكرّر — إعادة التسجيل تُجدّد الوصول ولا تُنشئ
 * سجلاً ثانياً يفقد معه الطالب تقدّمه.
 */
final class EnrollStudent
{
    public function handle(User $user, Course $course, string $source = 'manual', ?int $orderItemId = null): Enrollment
    {
        $existing = Enrollment::where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->first();

        if ($existing !== null) {
            return $this->renew($existing, $course);
        }

        if (! $course->isOpenForEnrollment() && $source !== 'manual') {
            throw new RuntimeException('التسجيل في هذا الكورس مغلق الآن.');
        }

        $enrollment = Enrollment::create([
            'user_id' => $user->getKey(),
            'course_id' => $course->getKey(),
            'source' => $source,
            'order_item_id' => $orderItemId,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => $course->accessEndsAt(),
        ]);

        $course->increment('students_count');
        $course->instructor?->increment('students_count');

        notify('lms.enrolled', $user, [
            'course_title' => (string) $course->title,
            'course_url' => url('/learn/'.$course->slug),
            'instructor_name' => (string) ($course->instructor?->name() ?? ''),
            'url' => url('/learn/'.$course->slug),
        ]);

        return $enrollment;
    }

    /** تجديد وصول انتهى — يُبقي التقدّم والدرجات كما هي. */
    private function renew(Enrollment $enrollment, Course $course): Enrollment
    {
        if ($enrollment->hasAccess() && $enrollment->status !== 'refunded') {
            return $enrollment;
        }

        $enrollment->forceFill([
            'status' => $enrollment->progress_percent >= 100 ? 'completed' : 'active',
            'expires_at' => $course->accessEndsAt(),
        ])->save();

        return $enrollment;
    }
}
