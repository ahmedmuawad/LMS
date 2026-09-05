<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Ability;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\LearningRule;
use App\Modules\Lms\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * قواعد التفريع في منهج كورس.
 *
 * المنهج خطٌّ واحد: من رسب في اختبار الوحدة يمضي كمن أتقنها، ومن
 * أتقنها يُجبَر على مراجعةٍ لا يحتاجها. والقاعدة هنا جملةٌ واحدة
 * يقرؤها المدرّس ويفهمها: «إن كانت نتيجته دون كذا فافتح له هذا».
 */
final class LearningRuleController
{
    public function index(Request $request, int $courseId): View
    {
        $this->authorise($request);

        $course = Course::findOrFail($courseId);
        $items = CourseItem::where('course_id', $course->getKey())
            ->with('itemable')->orderBy('position')->get();

        return view('admin.learning-rules', [
            'course' => $course,
            'rules' => LearningRule::where('course_id', $course->getKey())
                ->with(['trigger.itemable', 'target.itemable'])->get(),

            // المُطلِق اختبارٌ لا غير: نتيجةٌ لا تُقاس لا تُفرّع
            'quizzes' => $items->filter(fn (CourseItem $i): bool => $i->itemable_type === Quiz::class),
            'items' => $items,
        ]);
    }

    public function store(Request $request, int $courseId): RedirectResponse
    {
        $this->authorise($request);

        $course = Course::findOrFail($courseId);

        $input = $request->validate([
            'trigger_item_id' => ['required', 'integer', 'exists:course_items,id'],
            'comparison' => ['required', 'string', 'in:below,above'],
            'threshold' => ['required', 'integer', 'min:0', 'max:100'],
            'target_item_id' => ['required', 'integer', 'different:trigger_item_id', 'exists:course_items,id'],
            'blocks_progress' => ['nullable', 'boolean'],
        ]);

        LearningRule::create([
            'course_id' => $course->getKey(),
            ...$input,
            'blocks_progress' => $request->boolean('blocks_progress'),
        ]);

        return back()->with('status', __('أُضيفت القاعدة.'));
    }

    public function destroy(Request $request, int $courseId, int $id): RedirectResponse
    {
        $this->authorise($request);

        LearningRule::where('course_id', $courseId)->findOrFail($id)->delete();

        return back()->with('status', __('حُذفت القاعدة.'));
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::COURSES_MANAGE), 403);
    }
}
