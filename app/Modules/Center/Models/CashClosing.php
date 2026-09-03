<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تقفيل يومي: ما تقوله السجلات مقابل ما عُدّ في الدرج.
 * الفرق يُسجَّل ويُبرَّر ولا يُطمس — هذا ما يوقف تسرّب النقد.
 */
final class CashClosing extends Model
{
    protected $table = 'center_cash_closings';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['closed_on' => 'date'];
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function difference(): Money
    {
        return Money::fromMinor((int) $this->difference_minor, (string) ($this->cashbox?->currency ?? 'EGP'));
    }

    public function isBalanced(): bool
    {
        return (int) $this->difference_minor === 0;
    }
}
