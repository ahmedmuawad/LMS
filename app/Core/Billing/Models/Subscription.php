<?php

declare(strict_types=1);

namespace App\Core\Billing\Models;

use App\Core\Entitlements\Models\Plan;
use App\Core\Support\Money;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property string $status
 * @property string $currency
 * @property int $amount_minor
 */
final class Subscription extends Model
{
    use CentralConnection;

    public const STATUSES = [
        'trialing' => 'تجربة مجانية',
        'active' => 'نشط',
        'past_due' => 'متعثّر',
        'paused' => 'موقوف مؤقتاً',
        'cancelled' => 'ملغى',
        'expired' => 'منتهٍ',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'auto_renew' => 'boolean',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_key', 'key');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function amount(): Money
    {
        return Money::fromMinor($this->amount_minor, $this->currency);
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['trialing', 'active', 'past_due'], true);
    }

    /** الإيراد الشهري المكافئ — السنوي يُقسَّم على ١٢ لا يُحسب كاملاً في شهره. */
    public function monthlyEquivalent(): Money
    {
        $months = max(1, $this->interval === 'year' ? $this->interval_count * 12 : $this->interval_count);

        return Money::fromMinor((int) round($this->amount_minor / $months), $this->currency);
    }

    public function daysUntilRenewal(): ?int
    {
        return $this->current_period_end === null
            ? null
            : (int) ceil(now()->floatDiffInDays($this->current_period_end, false));
    }
}
