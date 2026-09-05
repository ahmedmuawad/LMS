<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Lms\Actions\GradeQuizAttempt;
use App\Modules\Lms\Actions\StartQuizAttempt;
use App\Modules\Lms\Models\AttemptEvent;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Question;
use App\Modules\Lms\Models\Quiz;
use App\Modules\Lms\Models\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class QuizController
{
    public function start(Request $request, string $slug, string $itemId, StartQuizAttempt $action): RedirectResponse
    {
        [$course, $enrollment, $item] = $this->resolve($request, $slug, $itemId);

        try {
            $attempt = $action->handle($enrollment, $item->itemable);
        } catch (RuntimeException $e) {
            return back()->withErrors(['quiz' => $e->getMessage()]);
        }

        return redirect(url('/learn/'.$course->slug.'/quiz/'.$item->getKey().'/attempt/'.$attempt->getKey()));
    }

    public function attempt(Request $request, string $slug, string $itemId, string $attemptId): View
    {
        [$course, $enrollment, $item] = $this->resolve($request, $slug, $itemId);

        $attempt = QuizAttempt::where('enrollment_id', $enrollment->getKey())->findOrFail($attemptId);

        // انتهاء الوقت لا يترك الورقة مفتوحة: تُسلَّم بما أُجيب
        if ($attempt->isOpen() && $attempt->hasRunOut()) {
            app(GradeQuizAttempt::class)->handle($attempt, []);
            $attempt->refresh();
        }

        return view('lms.quiz', [
            'course' => $course,
            'enrollment' => $enrollment,
            'item' => $item,
            'quiz' => $item->itemable,
            'attempt' => $attempt,
            // الحلّ يُجلب هنا لا في اللقطة: ورقة مفتوحة لا حلّ معها
            'correct' => $this->correctFor($attempt, $item->itemable),
        ]);
    }

    /**
     * تسجيل حدث مراقبة — يُنادى من المتصفّح بـ`sendBeacon`.
     *
     * يردّ فارغاً دائماً ولا يكشف شيئاً: الطالب هو من يرسل، فلا
     * نخبره كم بقي له من مخالفات ولا متى يُنهى امتحانه — وإلا
     * قايس حدّه واقترب منه بأمان.
     *
     * والمحاولة المغلقة تُرفض بصمت: حدثٌ بعد التسليم لا معنى له،
     * وردُّ خطأٍ عليه يفتح باباً لقياس حالة المحاولة من الخارج.
     */
    public function event(Request $request, string $slug, string $itemId, string $attemptId): Response
    {
        [, $enrollment] = $this->resolve($request, $slug, $itemId);

        $attempt = QuizAttempt::where('enrollment_id', $enrollment->getKey())->find($attemptId);

        if ($attempt === null || ! $attempt->isOpen()) {
            return response()->noContent();
        }

        $input = $request->validate([
            'kind' => ['required', 'string', 'in:'.implode(',', array_keys(AttemptEvent::KINDS))],
            'at_second' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        AttemptEvent::create([
            'attempt_id' => $attempt->getKey(),
            'kind' => $input['kind'],
            'at_second' => (int) ($input['at_second'] ?? 0),
        ]);

        // العدّاد على المحاولة: قراءته لا تحتاج عدّ سطور السجلّ
        $attempt->increment('violations');

        return response()->noContent();
    }

    public function submit(Request $request, string $slug, string $itemId, string $attemptId, GradeQuizAttempt $action): RedirectResponse
    {
        [$course, $enrollment, $item] = $this->resolve($request, $slug, $itemId);

        $attempt = QuizAttempt::where('enrollment_id', $enrollment->getKey())->findOrFail($attemptId);

        abort_unless($attempt->isOpen(), 409, __('هذه المحاولة سُلّمت بالفعل.'));

        // الورقة المُسلَّمة تلقائياً تُوسَم: المصحّح يقرؤها بعينٍ تعرف
        if ($request->boolean('auto_submitted')) {
            $attempt->forceFill(['auto_submitted' => true])->save();
        }

        $action->handle($attempt, (array) $request->input('answers', []));

        return redirect(url('/learn/'.$course->slug.'/quiz/'.$item->getKey().'/attempt/'.$attempt->getKey()))
            ->with('status', __('سُلّم اختبارك.'));
    }

    /**
     * الإجابات الصحيحة — للورقة المسلَّمة وحدها، وبإذن إعداد الاختبار.
     *
     * @return array<int, list<string>>
     */
    private function correctFor(QuizAttempt $attempt, mixed $quiz): array
    {
        $allowed = ! $attempt->isOpen() && match ($quiz?->show_answers) {
            'after_submit' => true,
            'after_pass' => (bool) $attempt->passed,
            default => false,
        };

        if (! $allowed) {
            return [];
        }

        return Question::whereIn('id', collect($attempt->snapshot ?? [])->pluck('id'))
            ->pluck('correct', 'id')
            ->map(fn ($v): array => array_map('strval', (array) $v))
            ->all();
    }

    /** @return array{0: Course, 1: Enrollment, 2: CourseItem} */
    private function resolve(Request $request, string $slug, string $itemId): array
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $enrollment = Enrollment::where('user_id', $request->user()?->getKey())
            ->where('course_id', $course->getKey())
            ->first();

        abort_if($enrollment === null, 403, __('لست مسجّلاً في هذا الكورس.'));
        abort_unless($enrollment->hasAccess(), 403, __('انتهت مدة وصولك إلى هذا الكورس.'));

        $item = CourseItem::where('course_id', $course->getKey())
            ->where('itemable_type', Quiz::class)
            ->findOrFail($itemId);

        return [$course, $enrollment, $item];
    }
}
