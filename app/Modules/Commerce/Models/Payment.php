<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Payment extends Model
{
    public const STATUSES = [
        'pending' => 'قيد الانتظار',
        'authorized' => 'محجوز',
        'captured' => 'محصّل',
        'failed' => 'فشل',
        'refunded' => 'مستردّ',
        'cancelled' => 'ملغى',
    ];

    protected $guarded = [];

    protected $hidden = ['raw_request', 'raw_response'];

    protected function casts(): array
    {
        return [
            'raw_request' => 'array',
            'raw_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function amount(): Money
    {
        return Money::fromMinor((int) $this->amount_minor, $this->currency);
    }

    public function succeeded(): bool
    {
        return $this->status === 'captured';
    }
}
