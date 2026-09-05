<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قاعدة تفريع: نتيجةُ اختبارٍ تفتح عنصراً.
 */
final class LearningRule extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'course_id', 'trigger_item_id', 'comparison',
        'threshold', 'target_item_id', 'blocks_progress',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'integer',
            'blocks_progress' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(CourseItem::class, 'trigger_item_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(CourseItem::class, 'target_item_id');
    }

    public function matches(float $percentage): bool
    {
        return $this->comparison === 'below'
            ? $percentage < $this->threshold
            : $percentage >= $this->threshold;
    }

    public function describe(): string
    {
        return $this->comparison === 'below'
            ? __('إن كانت النتيجة دون :n٪', ['n' => $this->threshold])
            : __('إن كانت النتيجة :n٪ فأكثر', ['n' => $this->threshold]);
    }
}
