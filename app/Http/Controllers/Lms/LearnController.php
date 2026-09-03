<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Lms\Actions\BuildCurriculum;
use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Actions\TrackProgress;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** غرفة التعلّم: ما يراه الطالب بعد أن سجّل. */
final class LearnController
{
    public function __construct(
        private readonly BuildCurriculum $curriculum,
        private readonly TrackProgress $progress,
    ) {}

    public function enroll(Request $request, string $slug, EnrollStudent $action): RedirectResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        // المجاني وحده يُسجَّل مباشرة؛ المدفوع يمرّ بالسلة
        abort_unless($course->isFree(), 402, __('هذا الكورس مدفوع — أضفه إلى السلة لإتمام الشراء.'));

        try {
            $action->handle($request->user(), $course, 'free');
        } catch (RuntimeException $e) {
            return back()->withErrors(['enroll' => $e->getMessage()]);
        }

        return redirect(url('/learn/'.$course->slug))
            ->with('status', __('تم تسجيلك. ابدأ من أول درس.'));
    }

    public function room(Request $request, string $slug, ?string $itemId = null): View
    {
        [$course, $enrollment] = $this->resolve($request, $slug);

        $sections = $this->curriculum->handle($course, $enrollment);
        $item = $this->pickItem($course, $enrollment, $sections, $itemId);

        abort_if($item === null, 404, __('لا محتوى في هذا الكورس بعد.'));

        return view('lms.learn', [
            'course' => $course,
            'enrollment' => $enrollment,
            'sections' => $sections,
            'item' => $item,
            'neighbours' => $this->neighbours($sections, $item),
        ]);
    }

    /** حفظ موضع المشاهدة — يُنادى دورياً من المشغّل. */
    public function heartbeat(Request $request, string $slug, string $itemId): JsonResponse
    {
        [$course, $enrollment] = $this->resolve($request, $slug);

        $input = $request->validate([
            'position' => ['required', 'integer', 'min:0'],
            'watched' => ['nullable', 'integer', 'min:0'],
        ]);

        $item = CourseItem::where('course_id', $course->getKey())->findOrFail($itemId);

        $this->progress->watch($enrollment, $item, $input['position'], (int) ($input['watched'] ?? 0));

        return response()->json(['saved' => true]);
    }

    public function complete(Request $request, string $slug, string $itemId): RedirectResponse
    {
        [$course, $enrollment] = $this->resolve($request, $slug);

        $item = CourseItem::where('course_id', $course->getKey())->findOrFail($itemId);

        $this->progress->complete($enrollment, $item);

        $sections = $this->curriculum->handle($course, $enrollment->refresh());
        $next = $this->neighbours($sections, $item)['next'] ?? null;

        return redirect(url('/learn/'.$course->slug.($next ? '/'.$next['item']->getKey() : '')))
            ->with('status', $next === null ? __('أنهيت الكورس. مبروك!') : __('أُنجز. إلى التالي.'));
    }

    /** @return array{0: Course, 1: Enrollment} */
    private function resolve(Request $request, string $slug): array
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $enrollment = Enrollment::where('user_id', $request->user()?->getKey())
            ->where('course_id', $course->getKey())
            ->first();

        abort_if($enrollment === null, 403, __('لست مسجّلاً في هذا الكورس.'));
        abort_unless($enrollment->hasAccess(), 403, __('انتهت مدة وصولك إلى هذا الكورس.'));

        return [$course, $enrollment];
    }

    /** @param  list<array<string, mixed>>  $sections */
    private function pickItem(Course $course, Enrollment $enrollment, array $sections, ?string $itemId): ?CourseItem
    {
        $flat = collect($sections)->flatMap(fn (array $s): array => $s['items']);

        if ($itemId !== null) {
            $chosen = $flat->firstWhere(fn (array $row): bool => (string) $row['item']->getKey() === $itemId);

            abort_if($chosen === null, 404);
            abort_if($chosen['locked'], 403, $chosen['lock_reason'] ?? __('هذا المحتوى مقفل.'));

            return $chosen['item'];
        }

        // بلا اختيار: نُكمل من حيث توقّف، وإلا فأول عنصر مفتوح
        $resume = $flat->firstWhere(fn (array $r): bool => (string) $r['item']->getKey() === (string) $enrollment->last_item_id);

        if ($resume !== null && ! $resume['locked']) {
            return $resume['item'];
        }

        return $flat->first(fn (array $r): bool => ! $r['locked'])['item'] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return array{prev: ?array, next: ?array}
     */
    private function neighbours(array $sections, CourseItem $item): array
    {
        $flat = collect($sections)->flatMap(fn (array $s): array => $s['items'])->values();
        $index = $flat->search(fn (array $r): bool => $r['item']->is($item));

        return [
            'prev' => $index > 0 ? $flat[$index - 1] : null,
            'next' => $flat[$index + 1] ?? null,
        ];
    }
}
