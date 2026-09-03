<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Modules\Lms\Models\AssignmentSubmission;
use InvalidArgumentException;

final class GradeAssignment
{
    public function __construct(private readonly TrackProgress $progress) {}

    public function handle(AssignmentSubmission $submission, float $marks, ?array $feedback = null, ?int $graderId = null): AssignmentSubmission
    {
        $assignment = $submission->assignment;
        $max = (float) $assignment->max_marks;

        if ($marks < 0 || $marks > $max) {
            throw new InvalidArgumentException("الدرجة يجب أن تكون بين 0 و {$max}.");
        }

        // خصم التأخير يُطبَّق على ما استحقّه فعلاً، لا على الدرجة العظمى
        $final = $submission->is_late ? $assignment->applyLatePenalty($marks) : $marks;

        $submission->forceFill([
            'marks' => $final,
            'feedback' => $feedback,
            'status' => 'graded',
            'graded_by' => $graderId,
            'graded_at' => now(),
        ])->save();

        if ($submission->passed()) {
            $item = $assignment->items()->where('course_id', $submission->enrollment?->course_id)->first();

            if ($item !== null && $submission->enrollment !== null) {
                $this->progress->complete($submission->enrollment, $item);
            }
        }

        $student = $submission->enrollment?->user;

        if ($student !== null) {
            notify('lms.assignment_graded', $student, [
                'assignment_title' => (string) $assignment->title,
                'score' => $final.' / '.$max,
                'feedback' => (string) (is_array($feedback) ? ($feedback[$student->locale ?? 'ar'] ?? '') : ($feedback ?? '')),
                'submission_url' => url('/my-courses'),
                'url' => url('/my-courses'),
            ]);
        }

        return $submission;
    }

    public function requestResubmission(AssignmentSubmission $submission, ?array $feedback = null): AssignmentSubmission
    {
        $submission->forceFill([
            'status' => 'resubmit',
            'feedback' => $feedback,
            'graded_at' => now(),
        ])->save();

        return $submission;
    }
}
