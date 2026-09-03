<?php

declare(strict_types=1);

namespace App\Core\Billing\Models;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

final class InvoicePayment extends Model
{
    use CentralConnection;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function amount(): Money
    {
        return Money::fromMinor($this->amount_minor, $this->currency);
    }
}
