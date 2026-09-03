<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Observers;

use App\Modules\Commerce\Actions\SyncCourseProduct;
use App\Modules\Lms\Models\Course;

/**
 * المنتج يتبع الكورس تلقائياً.
 *
 * لو تُرك للمشترك لنسِيَه، فيصير الكورس منشوراً ولا يُشترى —
 * وهو أسوأ عطل: يبدو كل شيء سليماً ولا يصل بيع واحد.
 */
final class CourseObserver
{
    public function __construct(private readonly SyncCourseProduct $sync) {}

    public function saved(Course $course): void
    {
        if ($course->wasChanged(['status', 'price_minor', 'enrollment_type', 'title', 'excerpt', 'currency', 'max_students', 'cover_path'])
            || $course->wasRecentlyCreated) {
            $this->sync->handle($course);
        }
    }
}
