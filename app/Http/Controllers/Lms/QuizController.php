<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Lms\Actions\GradeQuizAttempt;
use App\Modules\Lms\Actions\StartQuizAttempt;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Quiz;
use App\Modules\Lms\Models\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

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
        ]);
    }

    public function submit(Request $request, string $slug, string $itemId, string $attemptId, GradeQuizAttempt $action): RedirectResponse
    {
        [$course, $enrollment, $item] = $this->resolve($request, $slug, $itemId);

        $attempt = QuizAttempt::where('enrollment_id', $enrollment->getKey())->findOrFail($attemptId);

        abort_unless($attempt->isOpen(), 409, __('هذه المحاولة سُلّمت بالفعل.'));

        $action->handle($attempt, (array) $request->input('answers', []));

        return redirect(url('/learn/'.$course->slug.'/quiz/'.$item->getKey().'/attempt/'.$attempt->getKey()))
            ->with('status', __('سُلّم اختبارك.'));
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
