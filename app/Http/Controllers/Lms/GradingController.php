<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Scope;
use App\Models\User;
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
 *
 * والطاولة محصورة بكورسات صاحبها: المدرّس يصحّح لطلابه هو، ولا
 * يقرأ اسم طالب في كورس غيره ولا يضع له درجة.
 */
final class GradingController
{
    public function __construct(private readonly Scope $scope) {}

    public function index(Request $request): View
    {
        return view('lms.grading', [
            'attempts' => $this->scope->byCourseVia(
                QuizAttempt::query()
                    ->where('status', 'submitted')
                    ->whereHas('answers', fn ($q) => $q->whereNull('is_correct')),
                $this->user($request),
                'enrollment',
            )
                ->with(['quiz', 'enrollment.user', 'enrollment.course'])
                ->oldest('submitted_at')
                ->limit(50)
                ->get(),

            'submissions' => $this->scope->byCourseVia(
                AssignmentSubmission::query()->where('status', 'pending'),
                $this->user($request),
                'enrollment',
            )
                ->with(['assignment', 'enrollment.user', 'enrollment.course'])
                ->oldest('submitted_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function attempt(Request $request, string $attemptId): View
    {
        return view('lms.grade-attempt', [
            'attempt' => $this->attemptFor($request, $attemptId)
                ->load(['quiz', 'answers.question', 'enrollment.user', 'enrollment.course']),
        ]);
    }

    public function gradeAnswer(Request $request, string $attemptId, string $answerId, GradeQuizAttempt $action): RedirectResponse
    {
        $this->attemptFor($request, $attemptId);

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

    public function submission(Request $request, string $submissionId): View
    {
        return view('lms.grade-submission', [
            'submission' => $this->submissionFor($request, $submissionId)
                ->load(['assignment', 'enrollment.user', 'enrollment.course']),
        ]);
    }

    public function gradeSubmission(Request $request, string $submissionId, GradeAssignment $action): RedirectResponse
    {
        $submission = $this->submissionFor($request, $submissionId);

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

    /** محاولة داخل نطاق صاحب الطلب — وإلا 404، لا 403 يكشف وجودها. */
    private function attemptFor(Request $request, string $attemptId): QuizAttempt
    {
        return $this->scope
            ->byCourseVia(QuizAttempt::query(), $this->user($request), 'enrollment')
            ->findOrFail($attemptId);
    }

    private function submissionFor(Request $request, string $submissionId): AssignmentSubmission
    {
        return $this->scope
            ->byCourseVia(AssignmentSubmission::query(), $this->user($request), 'enrollment')
            ->findOrFail($submissionId);
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
