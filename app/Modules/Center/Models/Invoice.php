<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * فاتورة قسط الطالب.
 *
 * @property string $status
 */
final class Invoice extends Model
{
    public const STATUSES = [
        'draft' => 'مسودّة',
        'unpaid' => 'غير مدفوعة',
        'partial' => 'مدفوعة جزئياً',
        'paid' => 'مدفوعة',
        'void' => 'ملغاة',
    ];

    protected $table = 'center_invoices';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CenterEnrollment::class, 'enrollment_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** المتأخرات: غير مدفوعة وتجاوز استحقاقها اليوم. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', ['unpaid', 'partial'])->whereDate('due_on', '<', now());
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['unpaid', 'partial']);
    }

    public function total(): Money
    {
        return Money::fromMinor((int) $this->total_minor, $this->currency);
    }

    public function paid(): Money
    {
        return Money::fromMinor((int) $this->paid_minor, $this->currency);
    }

    public function remaining(): Money
    {
        return Money::fromMinor(max(0, (int) $this->total_minor - (int) $this->paid_minor), $this->currency);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['unpaid', 'partial'], true)
            && $this->due_on !== null
            && $this->due_on->isPast();
    }

    public function daysLate(): int
    {
        return $this->isOverdue() ? (int) $this->due_on->diffInDays(now()) : 0;
    }
}
