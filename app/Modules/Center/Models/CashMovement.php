<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CashMovement extends Model
{
    public const TYPES = ['in' => 'وارد', 'out' => 'صادر', 'transfer' => 'تحويل'];

    protected $table = 'center_cash_movements';

    protected $guarded = [];

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function amount(): Money
    {
        return Money::fromMinor((int) $this->amount_minor, $this->currency);
    }

    public function balanceAfter(): Money
    {
        return Money::fromMinor((int) $this->balance_after_minor, $this->currency);
    }
}
