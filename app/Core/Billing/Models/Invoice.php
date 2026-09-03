<?php

declare(strict_types=1);

namespace App\Core\Billing\Models;

use App\Core\Support\Money;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property string $status
 * @property string $currency
 * @property array $lines
 */
final class Invoice extends Model
{
    use CentralConnection;

    public const STATUSES = [
        'draft' => 'مسودّة',
        'open' => 'مستحقّة',
        'paid' => 'مدفوعة',
        'overdue' => 'متأخّرة',
        'void' => 'ملغاة',
        'refunded' => 'مستردّة',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'billing_details' => 'array',
            'tax_rate' => 'decimal:2',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function total(): Money
    {
        return Money::fromMinor($this->total_minor, $this->currency);
    }

    public function outstanding(): Money
    {
        return Money::fromMinor(max(0, $this->total_minor - $this->paid_minor), $this->currency);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open'
            && $this->due_at !== null
            && $this->due_at->isPast();
    }
}
