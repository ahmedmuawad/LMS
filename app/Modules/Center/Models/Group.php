<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Core\Support\Money;
use App\Models\User;
use App\Modules\Lms\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * المجموعة: طلاب يجتمعون على مادة ومدرّس وموعد.
 */
final class Group extends Model
{
    use HasTranslations;

    public const STATUSES = [
        'draft' => 'مسودّة',
        'open' => 'مفتوحة للتسجيل',
        'running' => 'جارية',
        'finished' => 'منتهية',
        'cancelled' => 'ملغاة',
    ];

    public const PRICE_TYPES = [
        'monthly' => 'شهري',
        'per_session' => 'بالحصة',
        'full_term' => 'الترم كاملاً',
    ];

    protected $table = 'center_groups';

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return ['name' => 'array', 'start_date' => 'date', 'end_date' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** الكورس المسجّل المرتبط — به يراجع الغائب ما فاته. */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class)->orderBy('date');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CenterEnrollment::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'running']);
    }

    public function price(): Money
    {
        return Money::fromMinor((int) $this->price_minor, $this->currency);
    }

    public function seatsLeft(): int
    {
        return max(0, (int) $this->capacity - (int) $this->enrolled_count);
    }

    public function isFull(): bool
    {
        return $this->seatsLeft() === 0;
    }

    public function label(): string
    {
        return trim(($this->name ?? '').' — '.($this->subject?->name ?? ''), ' —');
    }
}
