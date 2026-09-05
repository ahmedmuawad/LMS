<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Core\Access\Ability;
use App\Models\User;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\LearningPath;
use App\Modules\Lms\Models\LearningPathEnrollment;
use App\Modules\Lms\Models\LearningPathItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * مسارات التعلّم — بانيها للمدرّس، وصفحتها للطالب.
 */
final class LearningPathController
{
    // ---------- الإدارة ----------

    public function courses(Request $request, int $pathId): View
    {
        $this->authorise($request);

        $path = LearningPath::with('items.course')->findOrFail($pathId);

        return view('admin.path-courses', [
            'path' => $path,
            'available' => Course::whereNotIn('id', $path->items->pluck('course_id'))
                ->orderBy('id')->limit(300)->get(),
        ]);
    }

    public function addCourse(Request $request, int $pathId): RedirectResponse
    {
        $this->authorise($request);

        $path = LearningPath::findOrFail($pathId);

        $input = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        LearningPathItem::firstOrCreate(
            ['path_id' => $path->getKey(), 'course_id' => (int) $input['course_id']],
            [
                'position' => (int) LearningPathItem::where('path_id', $path->getKey())->max('position') + 1,
                'is_required' => $request->boolean('is_required', true),
            ],
        );

        $this->refreshCount($path);

        return back()->with('status', __('أُضيف الكورس إلى المسار.'));
    }

    public function removeCourse(Request $request, int $pathId, int $itemId): RedirectResponse
    {
        $this->authorise($request);

        $path = LearningPath::findOrFail($pathId);

        LearningPathItem::where('path_id', $path->getKey())->whereKey($itemId)->delete();

        $this->refreshCount($path);

        return back()->with('status', __('أُزيل الكورس من المسار.'));
    }

    /** تحريك كورسٍ في الترتيب — الرحلة ترتيبٌ قبل كل شيء */
    public function move(Request $request, int $pathId, int $itemId): RedirectResponse
    {
        $this->authorise($request);

        $items = LearningPathItem::where('path_id', $pathId)->orderBy('position')->get();
        $index = $items->search(fn (LearningPathItem $i): bool => $i->getKey() === $itemId);

        if ($index === false) {
            return back();
        }

        $target = $request->input('direction') === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= $items->count()) {
            return back();
        }

        // التبديل بالموضع لا بالمعرّف: المواضع قد تتباعد بعد حذف
        $a = $items[$index];
        $b = $items[$target];

        [$a->position, $b->position] = [$b->position, $a->position];

        $a->save();
        $b->save();

        return back();
    }

    // ---------- الطالب والزائر ----------

    public function index(Request $request): View
    {
        $paths = LearningPath::published()
            ->when($request->user() === null, fn ($q) => $q->where('is_public', true))
            ->withCount('items')
            ->get();

        return view('lms.paths', [
            'paths' => $paths,
            'mine' => $request->user() === null ? collect() : LearningPathEnrollment::query()
                ->where('user_id', $request->user()->getKey())
                ->pluck('progress_percent', 'path_id'),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $path = LearningPath::published()->where('slug', $slug)
            ->with('items.course')
            ->firstOrFail();

        abort_if(! $path->is_public && $request->user() === null, 403);

        $user = $request->user();

        return view('lms.path', [
            'path' => $path,
            'progress' => $user instanceof User ? $path->progressFor($user) : 0,
            'next' => $user instanceof User ? $path->nextCourseFor($user) : null,
            'enrolled' => $user instanceof User && LearningPathEnrollment::where('path_id', $path->getKey())
                ->where('user_id', $user->getKey())->exists(),
            'unlocked' => $user instanceof User
                ? $path->items->mapWithKeys(fn (LearningPathItem $i): array => [
                    $i->course_id => $i->course !== null && $path->unlocks($user, $i->course),
                ])
                : collect(),
        ]);
    }

    public function join(Request $request, string $slug): RedirectResponse
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        $path = LearningPath::published()->where('slug', $slug)->firstOrFail();

        /*
         | الالتحاق بالمسار لا يُسجّل في كورساته.
         |
         | الكورسات لها أسعارها وشروطها، والمسار ترتيبٌ لها. ولو
         | سجّل فيها كلّها لأعطى مدفوعاً بلا دفع.
         */
        LearningPathEnrollment::firstOrCreate(
            ['path_id' => $path->getKey(), 'user_id' => $user->getKey()],
            ['started_at' => now(), 'progress_percent' => $path->progressFor($user)],
        );

        return back()->with('status', __('التحقتَ بالمسار — ابدأ من أوّل كورس فيه.'));
    }

    private function refreshCount(LearningPath $path): void
    {
        $path->forceFill([
            'courses_count' => LearningPathItem::where('path_id', $path->getKey())->count(),
        ])->save();
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::COURSES_MANAGE), 403);
    }
}
