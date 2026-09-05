<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * منطقة شحن: دولٌ وسعرٌ وحدُّ مجّانية.
 *
 * ## سعرٌ واحد للعالم لا يصحّ
 *
 * كان الشحن رقماً ثابتاً في الإعدادات: مشترٍ في القاهرة ومشترٍ في
 * الرياض يدفعان الشيء نفسه، والتكلفة الحقيقية تضاعفت. فيَخسر
 * المشترك في الشحنة البعيدة أو يُغالي في القريبة.
 *
 * ## والمنطقة تجمع الدول
 *
 * «الخليج» منطقةٌ بستّ دول وسعرٍ واحد؛ وصفٌّ لكل دولة يجعل تغيير
 * السعر ستّ تعديلات يُنسى أحدها فتبقى دولةٌ بسعر العام الماضي.
 */
final class ShippingZone extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name', 'countries', 'rate_minor', 'free_over_minor', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'countries' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * المنطقة التي تخدم هذه الدولة — أو null.
     *
     * وترتيبُ الأولوية `sort_order`: منطقةٌ عامّة تحمل `*` تُوضع
     * أخيراً كي لا تبتلع ما قبلها.
     */
    public static function forCountry(?string $country): ?self
    {
        if (blank($country)) {
            return null;
        }

        $country = mb_strtoupper($country);

        return self::active()->orderBy('sort_order')->get()
            ->first(function (self $zone) use ($country): bool {
                $countries = array_map('mb_strtoupper', (array) $zone->countries);

                // `*` منطقةُ «بقيّة العالم» — تُغني عن تعداد مئتَي دولة
                return in_array('*', $countries, true) || in_array($country, $countries, true);
            });
    }

    /** كم يدفع مشترٍ صافيه هذا؟ */
    public function costFor(Money $net): Money
    {
        if ($this->free_over_minor > 0 && $net->minor >= (int) $this->free_over_minor) {
            return Money::zero($net->currency);
        }

        return Money::fromMinor((int) $this->rate_minor, $net->currency);
    }

    public function label(): string
    {
        $name = (array) $this->name;

        return (string) ($name[app()->getLocale()] ?? $name['ar'] ?? reset($name) ?: __('منطقة'));
    }
}
