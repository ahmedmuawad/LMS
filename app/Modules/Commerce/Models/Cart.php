<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * السلة تعيش للزائر قبل أن يسجّل، وتُنقل إليه عند الدخول.
 * فقدان السلة عند التسجيل هو أسرع طريق لفقد البيع.
 */
final class Cart extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reminded_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function subtotal(): Money
    {
        return $this->items->reduce(
            fn (Money $carry, CartItem $item): Money => $carry->plus($item->total()),
            Money::zero($this->currency),
        );
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function count(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function hasShippable(): bool
    {
        return $this->items->contains(fn (CartItem $item): bool => (bool) $item->product?->requires_shipping);
    }
}
