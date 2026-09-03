<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Coupon extends Model
{
    use HasTranslations;

    public const TYPES = [
        'percent' => 'نسبة مئوية',
        'fixed' => 'مبلغ ثابت',
        'free_shipping' => 'شحن مجاني',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'applies_to' => 'array',
            'first_order_only' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'value' => 'float',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function hasStarted(): bool
    {
        return $this->starts_at === null || $this->starts_at->isPast();
    }

    public function hasExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    /** الخصم على مبلغ بعينه، بحدّ أقصى إن وُجد ولا يتجاوز المبلغ نفسه. */
    public function discountOn(Money $subtotal): Money
    {
        $raw = match ($this->type) {
            'percent' => $subtotal->percentage($this->value),
            'fixed' => Money::fromMinor((int) round($this->value * 100), $subtotal->currency),
            default => Money::zero($subtotal->currency),
        };

        $cap = $this->max_discount_minor;

        if ($cap !== null && $raw->minor > $cap) {
            $raw = Money::fromMinor((int) $cap, $subtotal->currency);
        }

        // لا يتحوّل الخصم إلى رصيد: أقصاه قيمة الطلب
        return $raw->minor > $subtotal->minor ? $subtotal : $raw;
    }
}
