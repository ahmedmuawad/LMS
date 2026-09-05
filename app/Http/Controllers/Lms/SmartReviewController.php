<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Models\User;
use App\Modules\Lms\Actions\AnswerReviewItem;
use App\Modules\Lms\Models\ReviewItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * المراجعة الذكية — ما أخطأ فيه الطالب يعود إليه حتى يُتقنه.
 *
 * ## سؤالٌ واحد في الصفحة
 *
 * قائمةٌ بعشرين سؤالاً تُقرأ كامتحان، ومن يراها يؤجّلها. وسؤالٌ
 * واحد بجوابٍ فوري وشرحٍ يُقرأ كتدريب، ويُكمَل في دقائق متفرّقة —
 * وهي الطريقة التي يُراجَع بها فعلاً.
 */
final class SmartReviewController
{
    public function index(Request $request): View
    {
        $user = $this->user($request);

        // الشاشة تُقفل مع الميزة: رابطٌ في القائمة يفتح صفحةً فارغة أسوأ من غيابه
        abort_unless((bool) setting('lms.review_enabled', true), 404);

        $pending = ReviewItem::where('user_id', $user->getKey())->pending();

        return view('lms.student.review', [
            'pending' => (clone $pending)->count(),
            'mastered' => ReviewItem::where('user_id', $user->getKey())->whereNotNull('mastered_at')->count(),

            /*
             | التجميع بالكورس ليعرف الطالب أين ضعفه.
             |
             | «عندك ١٢ سؤالاً» لا تدلّ على شيء؛ و«٩ منها في الكيمياء»
             | تدلّ على الدرس الذي يُعاد.
             */
            'byCourse' => (clone $pending)->with('course')
                ->get()
                ->groupBy('course_id')
                ->map(fn ($items) => [
                    'course' => $items->first()->course,
                    'count' => $items->count(),
                ])
                ->values(),
        ]);
    }

    /** سؤالٌ واحد: الأولى بالمراجعة الآن. */
    public function next(Request $request): View|RedirectResponse
    {
        $user = $this->user($request);

        $query = ReviewItem::where('user_id', $user->getKey())->due()->with('question');

        if ($request->filled('course')) {
            $query->where('course_id', (int) $request->input('course'));
        }

        $item = $query->first();

        if ($item === null || $item->question === null) {
            return redirect(url('/my-review'))
                ->with('status', __('لا أسئلة للمراجعة الآن — أحسنتَ.'));
        }

        return view('lms.student.review-question', [
            'item' => $item,
            'question' => $item->question,
            'remaining' => (clone $query)->count(),
            'course' => $request->input('course'),
        ]);
    }

    public function answer(Request $request, int $id, AnswerReviewItem $action): RedirectResponse
    {
        $user = $this->user($request);

        $item = ReviewItem::where('user_id', $user->getKey())->findOrFail($id);

        $result = $action->handle($item, $request->input('answer'));

        /*
         | النتيجة تُمرَّر في الجلسة لا في الرابط.
         |
         | رابطٌ فيه `?correct=1` يُشارَك ويُعاد فتحه فيُظهر صواباً
         | لسؤالٍ لم يُجَب — والجلسة تُقرأ مرّةً وتزول.
         */
        return redirect(url('/my-review/'.$item->getKey().'/result'))
            ->with('review_result', [
                'correct' => $result['correct'],
                'mastered' => $result['mastered'],
                'course' => $request->input('course'),
            ]);
    }

    public function result(Request $request, int $id): View|RedirectResponse
    {
        $user = $this->user($request);
        $result = $request->session()->get('review_result');

        if (! is_array($result)) {
            return redirect(url('/my-review'));
        }

        $item = ReviewItem::where('user_id', $user->getKey())->with('question')->findOrFail($id);

        return view('lms.student.review-result', [
            'item' => $item,
            'question' => $item->question,
            'correct' => $result['correct'] ?? null,
            'mastered' => (bool) ($result['mastered'] ?? false),
            'course' => $result['course'] ?? null,
            'remaining' => ReviewItem::where('user_id', $user->getKey())->pending()->count(),
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        return $user;
    }
}
