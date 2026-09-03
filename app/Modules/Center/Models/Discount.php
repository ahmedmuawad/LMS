<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Discount extends Model
{
    public const TYPES = [
        'sibling' => 'خصم أخوة',
        'excellence' => 'خصم تفوّق',
        'hardship' => 'حالة اجتماعية',
        'promo' => 'عرض ترويجي',
        'staff' => 'أبناء العاملين',
    ];

    protected $table = 'center_discounts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date', 'ends_on' => 'date',
            'is_active' => 'boolean', 'value' => 'float',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()));
    }

    public function amountOn(Money $price): Money
    {
        $discount = $this->value_type === 'percent'
            ? $price->percentage($this->value)
            : Money::fromMinor((int) round($this->value * 100), $price->currency);

        return $discount->minor > $price->minor ? $price : $discount;
    }
}
