<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\LessonProgress;

/**
 * تقدّم الطالب.
 *
 * النسبة تُحسب من عناصر المنهج كلها لا من الدروس وحدها: الطالب
 * الذي أنهى الفيديوهات وترك الاختبارات لم يُنهِ الكورس.
 */
final class TrackProgress
{
    public function __construct(private readonly IssueCertificate $certificates) {}

    public function watch(Enrollment $enrollment, CourseItem $item, int $positionSeconds, int $watchedSeconds = 0): LessonProgress
    {
        $progress = $this->record($enrollment, $item);

        $progress->forceFill([
            'status' => $progress->isComplete() ? 'completed' : 'in_progress',
            'last_position_seconds' => max(0, $positionSeconds),
            // المشاهدة تراكمية ولا تتراجع: الرجوع للخلف ليس مسحاً لما شوهد
            'watched_seconds' => max((int) $progress->watched_seconds, $watchedSeconds),
        ])->save();

        $enrollment->forceFill(['last_item_id' => $item->getKey()])->save();

        return $progress;
    }

    public function complete(Enrollment $enrollment, CourseItem $item): LessonProgress
    {
        $progress = $this->record($enrollment, $item);

        if (! $progress->isComplete()) {
            $progress->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        }

        $this->recalculate($enrollment);

        return $progress;
    }

    public function uncomplete(Enrollment $enrollment, CourseItem $item): void
    {
        $this->record($enrollment, $item)
            ->forceFill(['status' => 'in_progress', 'completed_at' => null])->save();

        $this->recalculate($enrollment);
    }

    /** يعيد حساب النسبة ويُصدر الشهادة عند بلوغ حدّها. */
    public function recalculate(Enrollment $enrollment): int
    {
        $total = CourseItem::where('course_id', $enrollment->course_id)->count();

        $done = LessonProgress::where('enrollment_id', $enrollment->getKey())
            ->where('status', 'completed')
            ->count();

        $percent = $total === 0 ? 0 : (int) floor($done / $total * 100);
        $finished = $percent >= 100;

        $enrollment->forceFill([
            'progress_percent' => $percent,
            'status' => $finished ? 'completed' : ($enrollment->status === 'completed' ? 'active' : $enrollment->status),
            'completed_at' => $finished ? ($enrollment->completed_at ?? now()) : null,
        ])->save();

        if ($finished) {
            $this->certificates->handle($enrollment->refresh());
        }

        return $percent;
    }

    private function record(Enrollment $enrollment, CourseItem $item): LessonProgress
    {
        return LessonProgress::firstOrCreate(
            ['enrollment_id' => $enrollment->getKey(), 'item_id' => $item->getKey()],
            ['status' => 'in_progress'],
        );
    }
}
