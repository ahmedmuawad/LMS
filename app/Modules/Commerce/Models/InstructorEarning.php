<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use App\Modules\Lms\Models\Instructor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InstructorEarning extends Model
{
    public const STATUSES = [
        'pending' => 'قيد الانتظار',
        'available' => 'متاح للتحويل',
        'paid' => 'محوَّل',
        'reversed' => 'مسترجَع',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['available_at' => 'datetime', 'rate' => 'float'];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function amount(): Money
    {
        return Money::fromMinor((int) $this->amount_minor, $this->currency);
    }

    /** ما نضج فعلاً: انقضت مهلة استرداده ولم يُحوَّل بعد. */
    public function scopeReadyToPay(Builder $query): Builder
    {
        return $query->where('status', 'available')
            ->whereNull('payout_id')
            ->where(fn (Builder $q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()));
    }
}
