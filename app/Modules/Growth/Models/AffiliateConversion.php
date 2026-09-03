<?php

declare(strict_types=1);

namespace App\Modules\Growth\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AffiliateConversion extends Model
{
    public const STATUSES = [
        'pending' => 'قيد النضج', 'approved' => 'مستحقّة', 'rejected' => 'مرفوضة', 'paid' => 'مدفوعة',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['matured_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePayable(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function commission(): Money
    {
        return Money::fromMinor((int) $this->commission_minor, (string) $this->currency);
    }

    public function amount(): Money
    {
        return Money::fromMinor((int) $this->amount_minor, (string) $this->currency);
    }

    /** العمولة تنضج بعد انقضاء مهلة الاسترداد لا لحظة البيع. */
    public function hasMatured(): bool
    {
        return $this->matured_at !== null && $this->matured_at->isPast();
    }
}
