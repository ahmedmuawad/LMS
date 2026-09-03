<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $status
 * @property string $currency
 */
final class Order extends Model
{
    public const STATUSES = [
        'pending' => 'قيد الإنشاء',
        'awaiting_payment' => 'بانتظار الدفع',
        'paid' => 'مدفوع',
        'processing' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغى',
        'refunded' => 'مستردّ',
        'failed' => 'فشل',
    ];

    /** الحالات التي يُسلَّم عندها ما اشتُري. */
    public const FULFILLED = ['paid', 'processing', 'completed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'billing' => 'array',
            'shipping' => 'array',
            'tax_rate' => 'decimal:2',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'awaiting_payment']);
    }

    public function total(): Money
    {
        return Money::fromMinor((int) $this->total_minor, $this->currency);
    }

    public function subtotal(): Money
    {
        return Money::fromMinor((int) $this->subtotal_minor, $this->currency);
    }

    public function discount(): Money
    {
        return Money::fromMinor((int) $this->discount_minor, $this->currency);
    }

    public function tax(): Money
    {
        return Money::fromMinor((int) $this->tax_minor, $this->currency);
    }

    public function shipping(): Money
    {
        return Money::fromMinor((int) $this->shipping_minor, $this->currency);
    }

    public function refunded(): Money
    {
        return Money::fromMinor((int) $this->refunded_minor, $this->currency);
    }

    /** ما دُفع فعلاً — مجموع الدفعات الناجحة لا حالة الطلب. */
    public function paid(): Money
    {
        return Money::fromMinor(
            (int) $this->payments()->where('status', 'captured')->sum('amount_minor'),
            $this->currency,
        );
    }

    public function outstanding(): Money
    {
        return Money::fromMinor(max(0, $this->total_minor - $this->paid()->minor), $this->currency);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, self::FULFILLED, true);
    }

    public function isRefundable(): bool
    {
        return $this->isPaid() && $this->refunded_minor < $this->total_minor;
    }

    public function customerName(): string
    {
        return (string) ($this->user?->name ?? $this->billing['name'] ?? __('ضيف'));
    }

    public function customerEmail(): ?string
    {
        return $this->user?->email ?? $this->guest_email ?? ($this->billing['email'] ?? null);
    }
}
