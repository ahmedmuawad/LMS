<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * كود الشحن — أهم وسيلة دفع في السوق المصري.
 *
 * الطالب يشتري كرتاً من مكتبة ويفتح به محتواه بلا بطاقة بنكية.
 * الكود يُستهلك مرة واحدة، ولا يُقرأ نصّه من القاعدة بعد الطباعة.
 */
final class RechargeCode extends Model
{
    public const STATUSES = [
        'unused' => 'غير مستخدم',
        'used' => 'مستخدم',
        'void' => 'ملغى',
        'expired' => 'منتهي',
    ];

    public const TYPES = [
        'wallet' => 'شحن رصيد',
        'course' => 'فتح كورس',
        'bundle' => 'فتح حزمة',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(RechargeBatch::class, 'batch_id');
    }

    public function scopeRedeemable(Builder $query): Builder
    {
        return $query->where('status', 'unused')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function value(): Money
    {
        return Money::fromMinor((int) $this->value_minor, $this->currency ?? tenant('currency') ?? 'EGP');
    }

    public function isRedeemable(): bool
    {
        return $this->status === 'unused'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * صيغة تُقرأ وتُكتب بيد إنسان: مجموعات من أربعة، بلا حروف
     | تلتبس بأرقام. الطالب سيكتبه من كرت مطبوع لا ينسخه.
     */
    public static function generate(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // بلا I O 0 1
        $groups = [];

        for ($group = 0; $group < 4; $group++) {
            $chunk = '';

            for ($i = 0; $i < 4; $i++) {
                $chunk .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $groups[] = $chunk;
        }

        return implode('-', $groups);
    }
}
