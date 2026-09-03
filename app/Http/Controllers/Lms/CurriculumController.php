<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Lms\Models\Assignment;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\CourseSection;
use App\Modules\Lms\Models\Lesson;
use App\Modules\Lms\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * باني المنهج: أقسام وعناصر بترتيب واحد.
 *
 * الدرس والاختبار والواجب كيانات مستقلة قابلة لإعادة الاستخدام،
 * وهذه الشاشة تضعها في ترتيب الكورس — فسؤال واحد قد يخدم اختبارين،
 * ودرس واحد قد يظهر في مسارين.
 */
final class CurriculumController
{
    public function show(string $courseId): View
    {
        $course = Course::with(['sections.items.itemable', 'instructor.user'])->findOrFail($courseId);

        return view('lms.curriculum', [
            'course' => $course,
            'orphans' => $course->items()->whereNull('section_id')->with('itemable')->get(),
            'lessons' => Lesson::latest()->limit(200)->get(),
            'quizzes' => Quiz::latest()->limit(200)->get(),
            'assignments' => Assignment::latest()->limit(200)->get(),
        ]);
    }

    public function addSection(Request $request, string $courseId): RedirectResponse
    {
        $course = Course::findOrFail($courseId);

        $input = $request->validate([
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string', 'max:200'],
        ]);

        abort_if(blank(array_filter($input['title'])), 422, __('القسم يحتاج عنواناً بلغة واحدة على الأقل.'));

        CourseSection::create([
            'course_id' => $course->getKey(),
            'title' => array_filter($input['title']),
            'position' => (int) $course->sections()->max('position') + 1,
        ]);

        return back()->with('status', __('أُضيف القسم.'));
    }

    public function addItem(Request $request, string $courseId): RedirectResponse
    {
        $course = Course::findOrFail($courseId);

        $input = $request->validate([
            'kind' => ['required', 'string', 'in:'.implode(',', array_keys(CourseItem::TYPES))],
            'itemable_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
        ]);

        $class = CourseItem::TYPES[$input['kind']];

        // نتحقّق من وجود الكيان بأنفسنا: المعرّف يأتي من المستخدم
        abort_unless($class::whereKey($input['itemable_id'])->exists(), 404, __('العنصر غير موجود.'));

        CourseItem::create([
            'course_id' => $course->getKey(),
            'section_id' => $input['section_id'] ?? null,
            'itemable_type' => $class,
            'itemable_id' => $input['itemable_id'],
            'position' => (int) $course->items()->max('position') + 1,
        ]);

        $this->refreshCounts($course);

        return back()->with('status', __('أُضيف العنصر إلى المنهج.'));
    }

    /** إعادة الترتيب بالسحب — تصل قائمة معرّفات بالترتيب الجديد. */
    public function reorder(Request $request, string $courseId): RedirectResponse
    {
        $course = Course::findOrFail($courseId);

        $input = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['integer'],
            'section_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($course, $input): void {
            foreach ($input['items'] as $position => $itemId) {
                CourseItem::where('course_id', $course->getKey())
                    ->whereKey($itemId)
                    ->update(['position' => $position, 'section_id' => $input['section_id'] ?? null]);
            }
        });

        return back()->with('status', __('حُفظ الترتيب.'));
    }

    public function removeItem(string $courseId, string $itemId): RedirectResponse
    {
        $course = Course::findOrFail($courseId);

        // الحذف من المنهج لا يحذف الدرس نفسه — قد يخدم كورساً آخر
        CourseItem::where('course_id', $course->getKey())->whereKey($itemId)->delete();

        $this->refreshCounts($course);

        return back()->with('status', __('أُزيل العنصر من المنهج. الدرس نفسه باقٍ في مكتبتك.'));
    }

    public function updateItem(Request $request, string $courseId, string $itemId): RedirectResponse
    {
        $input = $request->validate([
            'is_preview' => ['nullable', 'boolean'],
            'available_after_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);

        CourseItem::where('course_id', $courseId)->whereKey($itemId)->update([
            'is_preview' => (bool) ($input['is_preview'] ?? false),
            'available_after_days' => (int) ($input['available_after_days'] ?? 0),
        ]);

        return back()->with('status', __('حُدِّث العنصر.'));
    }

    public function removeSection(string $courseId, string $sectionId): RedirectResponse
    {
        $course = Course::findOrFail($courseId);

        $section = CourseSection::where('course_id', $course->getKey())->findOrFail($sectionId);

        // عناصر القسم تعود بلا قسم بدل أن تُحذف مع الحاوية
        CourseItem::where('section_id', $section->getKey())->update(['section_id' => null]);
        $section->delete();

        return back()->with('status', __('حُذف القسم وبقيت عناصره.'));
    }

    private function refreshCounts(Course $course): void
    {
        $items = $course->items()->with('itemable')->get();

        $course->forceFill([
            'lessons_count' => $items->count(),
            'duration_minutes' => (int) round($items
                ->filter(fn (CourseItem $i): bool => $i->itemable instanceof Lesson)
                ->sum(fn (CourseItem $i): int => (int) $i->itemable->duration_seconds) / 60),
        ])->save();
    }
}
