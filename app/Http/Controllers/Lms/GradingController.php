<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Lms\Actions\GradeAssignment;
use App\Modules\Lms\Actions\GradeQuizAttempt;
use App\Modules\Lms\Models\AssignmentSubmission;
use App\Modules\Lms\Models\QuizAnswer;
use App\Modules\Lms\Models\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * طاولة التصحيح.
 *
 * ما ينتظر إنساناً يُجمَع في مكان واحد مرتّباً بالأقدم: الطالب الذي
 * سلّم أولاً هو الأحقّ بأن يُصحَّح أولاً.
 */
final class GradingController
{
    public function index(): View
    {
        return view('lms.grading', [
            'attempts' => QuizAttempt::query()
                ->where('status', 'submitted')
                ->whereHas('answers', fn ($q) => $q->whereNull('is_correct'))
                ->with(['quiz', 'enrollment.user', 'enrollment.course'])
                ->oldest('submitted_at')
                ->limit(50)
                ->get(),

            'submissions' => AssignmentSubmission::query()
                ->where('status', 'pending')
                ->with(['assignment', 'enrollment.user', 'enrollment.course'])
                ->oldest('submitted_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function attempt(string $attemptId): View
    {
        return view('lms.grade-attempt', [
            'attempt' => QuizAttempt::with(['quiz', 'answers.question', 'enrollment.user', 'enrollment.course'])
                ->findOrFail($attemptId),
        ]);
    }

    public function gradeAnswer(Request $request, string $attemptId, string $answerId, GradeQuizAttempt $action): RedirectResponse
    {
        $answer = QuizAnswer::where('attempt_id', $attemptId)->findOrFail($answerId);

        $max = (float) ($answer->attempt?->snapshot[array_search(
            (int) $answer->question_id,
            array_column($answer->attempt->snapshot ?? [], 'id'),
            true,
        )]['marks'] ?? $answer->question?->marks ?? 0);

        $input = $request->validate([
            'marks' => ['required', 'numeric', 'min:0', 'max:'.$max],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->gradeAnswer(
            $answer,
            (float) $input['marks'],
            filled($input['note'] ?? null) ? ['ar' => $input['note']] : null,
            $request->user()?->getKey(),
        );

        return back()->with('status', __('حُفظت الدرجة.'));
    }

    public function submission(string $submissionId): View
    {
        return view('lms.grade-submission', [
            'submission' => AssignmentSubmission::with(['assignment', 'enrollment.user', 'enrollment.course'])
                ->findOrFail($submissionId),
        ]);
    }

    public function gradeSubmission(Request $request, string $submissionId, GradeAssignment $action): RedirectResponse
    {
        $submission = AssignmentSubmission::findOrFail($submissionId);

        $input = $request->validate([
            'marks' => ['nullable', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'action' => ['required', 'string', 'in:grade,resubmit'],
        ]);

        $feedback = filled($input['feedback'] ?? null) ? ['ar' => $input['feedback']] : null;

        if ($input['action'] === 'resubmit') {
            $action->requestResubmission($submission, $feedback);

            return redirect(url('/admin/grading'))->with('status', __('طُلبت إعادة التسليم.'));
        }

        try {
            $action->handle($submission, (float) ($input['marks'] ?? 0), $feedback, $request->user()?->getKey());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['marks' => $e->getMessage()]);
        }

        return redirect(url('/admin/grading'))->with('status', __('صُحّح الواجب.'));
    }
}
