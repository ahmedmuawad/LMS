<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\ExchangeRates;
use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * السلة تعيش للزائر قبل أن يسجّل، وتُنقل إليه عند الدخول.
 * فقدان السلة عند التسجيل هو أسرع طريق لفقد البيع.
 */
final class Cart extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reminded_at' => 'datetime',
            'expires_at' => 'datetime',
            'rate_locked_at' => 'datetime',
        ];
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

    /**
     * سعر الصرف المجمَّد لهذه السلة.
     *
     * يُثبَّت عند أوّل تسعير، ويُجدَّد بعد نصف ساعة. فمن رأى سعراً
     * وذهب يُحضر بطاقته يعود فيجده كما تركه — والفرق الذي نخسره
     * أقلّ ممّا نخسره ببيعةٍ تُلغى عند الدفع.
     */
    public function lockedRate(): float
    {
        $fresh = $this->rate_locked_at !== null
            && $this->rate_locked_at->diffInMinutes(now()) < ExchangeRates::FREEZE_MINUTES;

        if ($fresh && (float) $this->locked_rate > 0) {
            return (float) $this->locked_rate;
        }

        $rate = app(ExchangeRates::class)->rateFor($this->currency);

        $this->forceFill(['locked_rate' => $rate, 'rate_locked_at' => now()])->save();

        return $rate;
    }

    /** متى ينتهي تجميد السعر — تُعرَض للمشتري ليعرف أن عليه أن يُتمّ */
    public function rateExpiresAt(): ?Carbon
    {
        return $this->rate_locked_at?->copy()->addMinutes(ExchangeRates::FREEZE_MINUTES);
    }

    public function hasShippable(): bool
    {
        return $this->items->contains(fn (CartItem $item): bool => (bool) $item->product?->requires_shipping);
    }
}
