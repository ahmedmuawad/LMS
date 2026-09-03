<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CourseItem::class, 'item_id');
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }
}
