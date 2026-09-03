<?php

declare(strict_types=1);

namespace App\Modules\Growth\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مسوّق بالعمولة.
 *
 * النسبة تُقرأ من هنا إن وُجدت وإلا فالنسبة العامة: مسوّق مميّز
 * بنسبة أعلى شائع، وفرضه على الجميع يقتل هامش المنصّة.
 */
final class Affiliate extends Model
{
    public const STATUSES = [
        'pending' => 'بانتظار الموافقة', 'active' => 'نشط', 'suspended' => 'موقوف',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payout_details' => 'array',
            'commission_rate' => 'float',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function rate(): float
    {
        return $this->commission_rate ?? (float) setting('growth.affiliates_default_rate', 10);
    }

    public function earned(): Money
    {
        return Money::fromMinor((int) $this->earned_minor, (string) (tenant('currency') ?? 'EGP'));
    }

    public function balance(): Money
    {
        return Money::fromMinor(
            max(0, (int) $this->earned_minor - (int) $this->paid_minor),
            (string) (tenant('currency') ?? 'EGP'),
        );
    }

    /** نسبة التحويل: من كل مئة نقرة كم اشترى. */
    public function conversionRate(): float
    {
        return $this->clicks_count === 0
            ? 0.0
            : round((int) $this->conversions_count / (int) $this->clicks_count * 100, 1);
    }

    public function link(): string
    {
        return url('/?ref='.$this->code);
    }
}
