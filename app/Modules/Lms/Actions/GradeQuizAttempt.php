<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Modules\Lms\Models\Question;
use App\Modules\Lms\Models\QuizAnswer;
use App\Modules\Lms\Models\QuizAttempt;

/**
 * تصحيح محاولة.
 *
 * ما تصحّحه الآلة يُصحَّح فوراً؛ وما يحتاج بشراً يبقى معلَّقاً
 * ولا يُحتسب صفراً — إعطاء صفر لمقال لم يقرأه أحد ظلم مباشر.
 */
final class GradeQuizAttempt
{
    public function __construct(private readonly TrackProgress $progress) {}

    /** @param  array<int|string, mixed>  $answers  إجابات الطالب بمفتاح رقم السؤال */
    public function handle(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        $quiz = $attempt->quiz;
        $snapshot = collect($attempt->snapshot ?? [])->keyBy('id');
        $questions = Question::whereIn('id', $snapshot->keys())->get()->keyBy('id');

        $score = 0.0;
        $pending = false;

        foreach ($snapshot as $id => $frozen) {
            $question = $questions->get($id);

            if ($question === null) {
                continue;   // سؤال حُذف بعد بدء المحاولة — يُتخطّى ولا يُحتسب
            }

            $given = $answers[$id] ?? null;
            $marks = (float) ($frozen['marks'] ?? $question->marks);
            $correct = $given === null ? false : $question->grade($given);

            $awarded = match ($correct) {
                true => $marks,
                false => $quiz->negative_marking && $given !== null ? -(float) $question->negative_marks : 0.0,
                null => 0.0,
            };

            $pending = $pending || $correct === null;
            $score += $awarded;

            QuizAnswer::updateOrCreate(
                ['attempt_id' => $attempt->getKey(), 'question_id' => $id],
                ['answer' => ['value' => $given], 'is_correct' => $correct, 'marks_awarded' => $awarded],
            );
        }

        // الدرجة لا تنزل تحت الصفر مهما بلغت الدرجات السالبة
        $score = max(0, $score);
        $max = (float) $attempt->max_score;
        $percentage = $max > 0 ? round($score / $max * 100, 2) : 0.0;

        $attempt->forceFill([
            'status' => $pending ? 'submitted' : 'graded',
            'submitted_at' => now(),
            'time_spent_seconds' => (int) ($attempt->started_at?->diffInSeconds(now()) ?? 0),
            'score' => $score,
            'percentage' => $percentage,
            'passed' => ! $pending && $percentage >= (float) $quiz->passing_percentage,
            'evaluated_at' => $pending ? null : now(),
        ])->save();

        $this->markItemComplete($attempt);

        return $attempt->refresh();
    }

    /** درجة يدوية لسؤال مقالي، ثم إعادة جمع الدرجة كلها. */
    public function gradeAnswer(QuizAnswer $answer, float $marks, ?array $note = null, ?int $graderId = null): QuizAttempt
    {
        $answer->forceFill([
            'marks_awarded' => $marks,
            'is_correct' => $marks > 0,
            'instructor_note' => $note,
        ])->save();

        $attempt = $answer->attempt;
        $score = max(0, (float) $attempt->answers()->sum('marks_awarded'));
        $max = (float) $attempt->max_score;
        $percentage = $max > 0 ? round($score / $max * 100, 2) : 0.0;
        $stillPending = $attempt->answers()->whereNull('is_correct')->exists();

        $attempt->forceFill([
            'score' => $score,
            'percentage' => $percentage,
            'passed' => ! $stillPending && $percentage >= (float) $attempt->quiz->passing_percentage,
            'status' => $stillPending ? 'submitted' : 'graded',
            'evaluated_by' => $graderId,
            'evaluated_at' => $stillPending ? null : now(),
        ])->save();

        $this->markItemComplete($attempt->refresh());

        return $attempt;
    }

    /**
     * الاختبار يُحسب مكتملاً بالنجاح فيه لا بمجرّد تسليمه —
     * وإلا صار «أنهيت الكورس» بلا معنى.
     */
    private function markItemComplete(QuizAttempt $attempt): void
    {
        if (! $attempt->passed) {
            return;
        }

        $item = $attempt->quiz?->items()
            ->where('course_id', $attempt->enrollment?->course_id)
            ->first();

        if ($item !== null && $attempt->enrollment !== null) {
            $this->progress->complete($attempt->enrollment, $item);
        }
    }
}
