<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Modules\Lms\Models\Assignment;
use App\Modules\Lms\Models\AssignmentSubmission;
use App\Modules\Lms\Models\Enrollment;
use RuntimeException;

final class SubmitAssignment
{
    /** @param  list<array<string, mixed>>  $files */
    public function handle(Enrollment $enrollment, Assignment $assignment, ?string $content, array $files = []): AssignmentSubmission
    {
        $used = AssignmentSubmission::where('enrollment_id', $enrollment->getKey())
            ->where('assignment_id', $assignment->getKey())
            ->count();

        if ($used > 0 && $used > (int) $assignment->max_resubmissions) {
            throw new RuntimeException('استُنفدت مرات إعادة التسليم.');
        }

        $due = $assignment->dueFor($enrollment->started_at);
        $late = $due !== null && $due->isPast();

        if ($late && ! $assignment->allow_late) {
            throw new RuntimeException('انقضى موعد التسليم ولا يقبل هذا الواجب تسليماً متأخراً.');
        }

        if (blank($content) && $files === []) {
            throw new RuntimeException('لا يمكن تسليم واجب فارغ.');
        }

        return AssignmentSubmission::create([
            'enrollment_id' => $enrollment->getKey(),
            'assignment_id' => $assignment->getKey(),
            'attempt_no' => $used + 1,
            'content' => $content,
            'files' => $files,
            'submitted_at' => now(),
            'is_late' => $late,
            'status' => 'pending',
        ]);
    }
}
