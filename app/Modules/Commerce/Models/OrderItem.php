<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * بند الطلب يحمل نسخة مجمّدة من العنوان والسعر.
 * تغيير اسم الكورس أو سعره بعد شهر لا يجوز أن يغيّر فاتورة صدرت.
 */
final class OrderItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['title_snapshot' => 'array', 'fulfilled_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }

    public function title(): string
    {
        $snapshot = $this->title_snapshot ?? [];

        return (string) ($snapshot[app()->getLocale()]
            ?? $snapshot[config('locales.default', 'ar')]
            ?? reset($snapshot)
            ?: __('بند'));
    }

    public function unitPrice(): Money
    {
        return Money::fromMinor((int) $this->unit_price_minor, $this->order?->currency ?? 'EGP');
    }

    public function total(): Money
    {
        return Money::fromMinor((int) $this->total_minor, $this->order?->currency ?? 'EGP');
    }

    public function commission(): Money
    {
        return Money::fromMinor((int) $this->commission_minor, $this->order?->currency ?? 'EGP');
    }

    public function isFulfilled(): bool
    {
        return $this->fulfilled_at !== null;
    }
}
