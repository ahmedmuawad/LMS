<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Scope;
use App\Models\User;
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
 *
 * وكل مدخل هنا يمرّ بـ`courseFor`: الشاشة كانت تفتح بأي رقم كورس،
 * فكان المدرّس يحرّر منهج غيره برابط مكتوب باليد.
 */
final class CurriculumController
{
    public function __construct(private readonly Scope $scope) {}

    public function show(Request $request, string $courseId): View
    {
        $course = $this->courseFor($request, $courseId)
            ->load(['sections.items.itemable', 'instructor.user']);

        $user = $this->user($request);

        return view('lms.curriculum', [
            'course' => $course,
            'orphans' => $course->items()->whereNull('section_id')->with('itemable')->get(),
            // البنوك مشتركة، وما يراه المدرّس منها ما أنشأه هو
            'lessons' => $this->scope->byCreator(Lesson::query(), $user)->latest()->limit(200)->get(),
            'quizzes' => $this->scope->byCreator(Quiz::query(), $user)->latest()->limit(200)->get(),
            'assignments' => $this->scope->byCreator(Assignment::query(), $user)->latest()->limit(200)->get(),
        ]);
    }

    public function addSection(Request $request, string $courseId): RedirectResponse
    {
        $course = $this->courseFor($request, $courseId);

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
        $course = $this->courseFor($request, $courseId);

        $input = $request->validate([
            'kind' => ['required', 'string', 'in:'.implode(',', array_keys(CourseItem::TYPES))],
            'itemable_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
        ]);

        $class = CourseItem::TYPES[$input['kind']];

        // نتحقّق من وجود الكيان بأنفسنا: المعرّف يأتي من المستخدم،
        // ومن نطاقه أيضاً وإلا ضمّ المدرّس درس غيره إلى منهجه
        abort_unless(
            $this->scope->byCreator($class::query(), $this->user($request))
                ->whereKey($input['itemable_id'])->exists(),
            404,
            __('العنصر غير موجود.'),
        );

        // القسم يجب أن يكون قسماً في هذا الكورس لا في كورس آخر
        if (($input['section_id'] ?? null) !== null) {
            abort_unless(
                CourseSection::where('course_id', $course->getKey())
                    ->whereKey($input['section_id'])->exists(),
                404,
                __('القسم غير موجود.'),
            );
        }

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
        $course = $this->courseFor($request, $courseId);

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

    public function removeItem(Request $request, string $courseId, string $itemId): RedirectResponse
    {
        $course = $this->courseFor($request, $courseId);

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

        $course = $this->courseFor($request, $courseId);

        CourseItem::where('course_id', $course->getKey())->whereKey($itemId)->update([
            'is_preview' => (bool) ($input['is_preview'] ?? false),
            'available_after_days' => (int) ($input['available_after_days'] ?? 0),
        ]);

        return back()->with('status', __('حُدِّث العنصر.'));
    }

    public function removeSection(Request $request, string $courseId, string $sectionId): RedirectResponse
    {
        $course = $this->courseFor($request, $courseId);

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

    /** الكورس إن كان كورس صاحب الطلب — وإلا 404. */
    private function courseFor(Request $request, string $courseId): Course
    {
        return $this->scope
            ->byInstructor(Course::query(), $this->user($request), 'instructor_id')
            ->findOrFail($courseId);
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
