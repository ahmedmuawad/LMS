<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * عنصر في المنهج: درس أو اختبار أو واجب في ترتيب واحد.
 *
 * @property string $itemable_type
 */
final class CourseItem extends Model
{
    /** الأنواع المسموحة — قائمة مغلقة، فلا يصل نوع من الخارج. */
    public const TYPES = [
        'lesson' => Lesson::class,
        'quiz' => Quiz::class,
        'assignment' => Assignment::class,
    ];

    public const ICONS = ['lesson' => '▶', 'quiz' => '◫', 'assignment' => '✎'];

    public const LABELS = ['lesson' => 'درس', 'quiz' => 'اختبار', 'assignment' => 'واجب'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_preview' => 'boolean', 'available_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'section_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function kind(): string
    {
        return (string) array_search($this->itemable_type, self::TYPES, true) ?: 'lesson';
    }

    public function label(): string
    {
        return __(self::LABELS[$this->kind()] ?? 'عنصر');
    }

    public function icon(): string
    {
        return self::ICONS[$this->kind()] ?? '•';
    }

    public function title(): ?string
    {
        return $this->itemable?->title;
    }

    /**
     * متى يُفتح هذا العنصر لمن سجّل في تاريخ بعينه.
     * الفتح التدريجي وعدٌ للطالب بأن المحتوى يصله على مهل، لا حجب عشوائي.
     */
    public function unlocksAt(?Carbon $enrolledAt): ?Carbon
    {
        if ($this->available_at !== null) {
            return $this->available_at;
        }

        $days = (int) $this->available_after_days;

        return $days > 0 && $enrolledAt !== null ? $enrolledAt->copy()->addDays($days) : null;
    }
}
