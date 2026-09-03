<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دفتر المحفظة — مجموع لا رصيد مخزَّن.
 *
 * كل حركة تحمل الرصيد بعدها، فيُقرأ التاريخ كما يُقرأ كشف حساب،
 * ويُكتشف أي خلل بالمقارنة لا بالثقة في عمود واحد.
 */
final class WalletTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amount(): Money
    {
        return Money::fromMinor((int) $this->amount_minor, $this->currency);
    }

    public function balanceAfter(): Money
    {
        return Money::fromMinor((int) $this->balance_after_minor, $this->currency);
    }

    public static function balanceFor(int $userId, string $currency): Money
    {
        $minor = (int) self::where('user_id', $userId)
            ->where('currency', $currency)
            ->orderByDesc('id')
            ->value('balance_after_minor');

        return Money::fromMinor($minor, $currency);
    }
}
