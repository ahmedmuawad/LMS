<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CenterEnrollment extends Model
{
    public const STATUSES = [
        'active' => 'نشط',
        'paused' => 'موقوف مؤقتاً',
        'transferred' => 'منقول',
        'dropped' => 'منسحب',
    ];

    protected $table = 'center_enrollments';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** ما يدفعه هذا الطالب فعلاً بعد خصمه الخاص. */
    public function netPrice(): Money
    {
        return Money::fromMinor(
            max(0, (int) $this->price_minor - (int) $this->discount_minor),
            $this->currency,
        );
    }
}
