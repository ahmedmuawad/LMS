<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ADR-014 — النسب والقواعد هنا قيم افتراضية قابلة للتعديل من لوحة الإدارة،
 * ويجب مراجعتها مع مستشار ضريبي في كل دولة قبل التفعيل التجاري.
 */
final class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['EG', 'مصر', 'Egypt', '+20', 'EGP', 'Africa/Cairo', true, 14.0, 'ضريبة القيمة المضافة', 'VAT',
                ['paymob', 'fawry', 'bank_transfer', 'wallet', 'recharge_code', 'stripe'], 'eta', 6],
            ['SA', 'السعودية', 'Saudi Arabia', '+966', 'SAR', 'Asia/Riyadh', true, 15.0, 'ضريبة القيمة المضافة', 'VAT',
                ['moyasar', 'tap', 'hyperpay', 'stripe', 'bank_transfer'], 'zatca', 0],
            ['AE', 'الإمارات', 'United Arab Emirates', '+971', 'AED', 'Asia/Dubai', true, 5.0, 'ضريبة القيمة المضافة', 'VAT',
                ['tap', 'stripe', 'bank_transfer'], null, 1],
            ['KW', 'الكويت', 'Kuwait', '+965', 'KWD', 'Asia/Kuwait', false, 0, null, null,
                ['tap', 'stripe'], null, 6],
            ['QA', 'قطر', 'Qatar', '+974', 'QAR', 'Asia/Qatar', false, 0, null, null,
                ['tap', 'stripe'], null, 0],
            ['JO', 'الأردن', 'Jordan', '+962', 'JOD', 'Asia/Amman', true, 16.0, 'ضريبة المبيعات', 'GST',
                ['hyperpay', 'stripe'], null, 0],
            ['MA', 'المغرب', 'Morocco', '+212', 'MAD', 'Africa/Casablanca', true, 20.0, 'الضريبة على القيمة المضافة', 'TVA',
                ['stripe'], null, 1],
        ];

        foreach ($rows as $i => [$code, $ar, $en, $dial, $cur, $tz, $taxOn, $rate, $taxAr, $taxEn, $gateways, $eInvoice, $weekStart]) {
            DB::table('countries')->updateOrInsert(['code' => $code], [
                'name' => json_encode(['ar' => $ar, 'en' => $en], JSON_UNESCAPED_UNICODE),
                'dial_code' => $dial,
                'currency' => $cur,
                'locale_default' => 'ar',
                'timezone_default' => $tz,
                'tax_enabled' => $taxOn,
                'tax_rate' => $rate,
                'tax_name' => $taxAr ? json_encode(['ar' => $taxAr, 'en' => $taxEn], JSON_UNESCAPED_UNICODE) : null,
                'tax_id_label' => $taxOn ? 'الرقم الضريبي' : null,
                'tax_inclusive_display' => true,
                'gateways' => json_encode($gateways),
                'e_invoice_provider' => $eInvoice,
                'week_start' => $weekStart,
                'numerals' => 'arabic',
                'calendar' => $code === 'SA' ? 'both' : 'gregorian',
                'is_active' => true,
                'position_order' => $i,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }
}
