<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\LessonProgress;

/**
 * منهج الكورس كما يراه هذا الطالب بعينه:
 * ما أنجزه، وما هو مفتوح، ولماذا أُقفل ما أُقفل.
 *
 * القفل يُشرح دائماً ولا يُترك غامضاً: «أكمل ما قبله» أو «يُفتح
 * يوم كذا» أو «للمشتركين». الغموض هنا يُقرأ عطلاً.
 */
final class BuildCurriculum
{
    /** @return list<array<string, mixed>> */
    public function handle(Course $course, ?Enrollment $enrollment = null): array
    {
        $items = $course->items()->with(['itemable', 'section'])->get();

        $done = $enrollment === null
            ? collect()
            : LessonProgress::where('enrollment_id', $enrollment->getKey())
                ->pluck('status', 'item_id');

        $sections = [];
        $previousIncomplete = false;

        foreach ($items as $item) {
            $key = $item->section_id ?? 0;

            $sections[$key] ??= [
                'id' => $item->section_id,
                'title' => $item->section?->title ?? __('محتوى الكورس'),
                'items' => [],
                'done' => 0,
                'total' => 0,
            ];

            $status = $done[$item->getKey()] ?? 'not_started';
            $complete = $status === 'completed';
            $lock = $this->lockReason($course, $item, $enrollment, $previousIncomplete);

            $sections[$key]['items'][] = [
                'item' => $item,
                'kind' => $item->kind(),
                'title' => $item->title(),
                'status' => $status,
                'completed' => $complete,
                'locked' => $lock !== null,
                'lock_reason' => $lock,
                'preview' => (bool) $item->is_preview,
            ];

            $sections[$key]['total']++;
            $sections[$key]['done'] += $complete ? 1 : 0;

            if ($course->sequential && ! $complete) {
                $previousIncomplete = true;
            }
        }

        return array_values($sections);
    }

    private function lockReason(Course $course, CourseItem $item, ?Enrollment $enrollment, bool $previousIncomplete): ?string
    {
        if ($item->is_preview) {
            return null;
        }

        if ($enrollment === null) {
            return __('سجّل في الكورس لفتح هذا المحتوى');
        }

        if (! $enrollment->hasAccess()) {
            return __('انتهت مدة وصولك إلى هذا الكورس');
        }

        $unlocks = $course->drip_enabled ? $item->unlocksAt($enrollment->started_at) : null;

        if ($unlocks !== null && $unlocks->isFuture()) {
            return __('يُفتح في :date', ['date' => $unlocks->translatedFormat('j F')]);
        }

        if ($course->sequential && $previousIncomplete) {
            return __('أكمل ما قبله أولاً');
        }

        return null;
    }
}
