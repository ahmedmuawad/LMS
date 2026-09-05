<?php

declare(strict_types=1);

namespace App\Core\Commerce;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * نسبة الضريبة — من دولة المشتري لا من رقمٍ واحد.
 *
 * ## جدولٌ كان يَعِد ولا يُقرَأ
 *
 * `countries` فيه `tax_enabled` و`tax_rate` و`tax_name` و`tax_id_label`
 * منذ البداية، وشاشةُ الإعدادات تقول صراحةً: «نِسَب الضريبة لكل دولة
 * تُدار من شاشة الدول والعملات في اللوحة العليا». والسلّة تقرأ
 * `currency.default_rate` وحده.
 *
 * فمشترٍ سعودي يُحسَب له ١٤٪ المصرية بدل ١٥٪، ومشترٍ إماراتي يُحسَب
 * له ١٤٪ وضريبتُه ٥٪. والفرق ليس خطأً في العرض: هو مالٌ يُحصَّل
 * باسم الضريبة ولا يُورَّد، أو يُورَّد ناقصاً — وكلاهما مساءلة.
 *
 * ## والافتراضي يبقى شبكة أمان
 *
 * دولةٌ ليست في الجدول، أو مشترٍ لم يُعلن بلده بعد، يأخذ النسبة
 * الافتراضية. وصفرٌ صامت أسوأ: يُبيع بلا ضريبةٍ ولا يُلاحَظ.
 */
final class TaxRates
{
    /**
     * النسبة المطبَّقة على مشترٍ في هذه الدولة.
     *
     * @param  string|null  $country  رمز ISO — أو null إن لم يُعرَف بعد
     */
    public function rateFor(?string $country): float
    {
        $row = $this->row($country);

        if ($row === null) {
            return $this->defaultRate();
        }

        /*
         | دولةٌ أُطفئت ضريبتُها صراحةً تعني صفراً لا الافتراضي.
         |
         | الكويت وقطر بلا ضريبةٍ على الخدمات؛ وإطفاؤها في الجدول
         | قرارٌ صريح، وتجاهلُه إلى الافتراضي يجعل المشترك يحصّل
         | ضريبةً لا وجود لها.
         */
        if (! (bool) $row->tax_enabled) {
            return 0.0;
        }

        return (float) $row->tax_rate;
    }

    /** اسم الضريبة كما يُكتب في الفاتورة — «ض.ق.م» أو «VAT» */
    public function nameFor(?string $country): string
    {
        $row = $this->row($country);

        $name = $row?->tax_name;

        if (is_string($name)) {
            $decoded = json_decode($name, true);
            $name = is_array($decoded) ? $decoded : $name;
        }

        if (is_array($name)) {
            $name = $name[app()->getLocale()] ?? $name['ar'] ?? reset($name);
        }

        return is_string($name) && $name !== '' ? $name : __('الضريبة');
    }

    /**
     * هل يُعرض السعر شاملاً الضريبة في هذه الدولة؟
     *
     * أوروبا تُلزم بعرضٍ شامل للمستهلك، والخليج يعرض الاثنين، وأمريكا
     * تضيفها عند الدفع. وقاعدةٌ واحدة للعالم تُخالف قانوناً في مكانٍ ما.
     */
    public function displaysInclusive(?string $country): bool
    {
        $row = $this->row($country);

        if ($row === null) {
            return setting('currency.prices_include_tax', 'inclusive') === 'inclusive';
        }

        return (bool) $row->tax_inclusive_display;
    }

    private function defaultRate(): float
    {
        return (float) setting('currency.default_rate', 0);
    }

    private function row(?string $country): ?object
    {
        if (blank($country)) {
            return null;
        }

        try {
            // الدول مرجعٌ مركزي: واحدةٌ للمنصّة كلّها لا لكل مشترك
            return DB::connection(config('tenancy.database.central_connection', 'sqlite'))
                ->table('countries')
                ->where('code', mb_strtoupper($country))
                ->first();
        } catch (Throwable) {
            return null;
        }
    }
}
