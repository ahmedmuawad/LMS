<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دفعة نقدية بإيصال مرقّم إلزامي.
 * «الطالب دفع ومحدش سجّل» لا تُحلّ إلا بإيصال لكل جنيه.
 */
final class Payment extends Model
{
    public const METHODS = [
        'cash' => 'نقداً',
        'card' => 'بطاقة',
        'wallet' => 'محفظة إلكترونية',
        'transfer' => 'تحويل',
        'online' => 'دفع أونلاين',
    ];

    protected $table = 'center_payments';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function amount(): Money
    {
        return Money::fromMinor((int) $this->amount_minor, $this->currency);
    }
}
