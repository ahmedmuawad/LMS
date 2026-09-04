<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Question;
use App\Modules\Lms\Models\Quiz;
use App\Modules\Lms\Models\QuizAttempt;
use RuntimeException;

/**
 * بدء محاولة اختبار.
 *
 * تُحفظ نسخة من الأسئلة داخل المحاولة: تعديل المدرّس للسؤال بعد
 * شهر لا يجوز أن يغيّر ورقة طالب صحّحت، ولا أن يفسد مراجعتها.
 */
final class StartQuizAttempt
{
    public function handle(Enrollment $enrollment, Quiz $quiz): QuizAttempt
    {
        $open = QuizAttempt::where('enrollment_id', $enrollment->getKey())
            ->where('quiz_id', $quiz->getKey())
            ->where('status', 'in_progress')
            ->first();

        // محاولة مفتوحة تُستأنف ولا تُستبدل — وإلا ضاع ما أجاب
        if ($open !== null) {
            return $open;
        }

        $used = QuizAttempt::where('enrollment_id', $enrollment->getKey())
            ->where('quiz_id', $quiz->getKey())
            ->count();

        if ($quiz->max_attempts > 0 && $used >= $quiz->max_attempts) {
            throw new RuntimeException('استُنفدت محاولاتك في هذا الاختبار.');
        }

        $this->assertCooldownPassed($enrollment, $quiz);

        $questions = $quiz->questionsForAttempt();

        return QuizAttempt::create([
            'enrollment_id' => $enrollment->getKey(),
            'quiz_id' => $quiz->getKey(),
            'attempt_no' => $used + 1,
            'status' => 'in_progress',
            'started_at' => now(),
            'max_score' => $questions->sum(fn (Question $q): float => $quiz->marksFor($q)),
            /*
             | اللقطة تحمل الشرح وخطوات الحل معها.
             |
             | لو قُرئا من السؤال وقت العرض لتغيّرا بعد تعديل المدرّس
             | للسؤال، فيرى الطالب شرحاً لا يخصّ الورقة التي أجابها.
             |
             | ولا تحمل **الإجابة الصحيحة**: اللقطة صفٌّ يُقرأ في أي
             | استجابة مستقبلية، ووضع الحلّ فيها يعني تسريبه لمن يفتح
             | أدوات المتصفّح وهو يمتحن.
             */
            'snapshot' => $questions->map(fn (Question $q): array => [
                'id' => $q->getKey(),
                'type' => $q->type,
                'body' => $q->getTranslations('body'),
                'options' => $quiz->shuffle_answers ? $this->shuffleOptions($q) : $q->options,
                'marks' => $quiz->marksFor($q),
                'difficulty' => $q->difficulty,
                'steps' => $q->getTranslations('steps'),
                'explanation' => $q->getTranslations('explanation'),
            ])->all(),
        ]);
    }

    private function assertCooldownPassed(Enrollment $enrollment, Quiz $quiz): void
    {
        $wait = (int) $quiz->retake_delay_hours;

        if ($wait === 0) {
            return;
        }

        $last = QuizAttempt::where('enrollment_id', $enrollment->getKey())
            ->where('quiz_id', $quiz->getKey())
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->value('submitted_at');

        if ($last !== null && $last->copy()->addHours($wait)->isFuture()) {
            throw new RuntimeException('لم تحن بعد فترة إعادة المحاولة.');
        }
    }

    /** @return array<int|string, mixed>|null */
    private function shuffleOptions(Question $question): ?array
    {
        $options = $question->options;

        if (! is_array($options) || $options === []) {
            return $options;
        }

        // نخلط مع الحفاظ على المفاتيح: المفتاح هو ما يُقارَن بالإجابة الصحيحة
        $keys = array_keys($options);
        shuffle($keys);

        return array_combine($keys, array_map(fn ($k) => $options[$k], $keys));
    }
}
