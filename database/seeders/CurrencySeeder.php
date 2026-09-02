<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // code, ar, en, symbol_ar, symbol_en, decimals, base
            ['EGP', 'جنيه مصري',  'Egyptian Pound', 'ج.م', 'EGP', 2, true],
            ['SAR', 'ريال سعودي', 'Saudi Riyal',    'ر.س', 'SAR', 2, false],
            ['AED', 'درهم إماراتي', 'UAE Dirham',    'د.إ', 'AED', 2, false],
            ['KWD', 'دينار كويتي', 'Kuwaiti Dinar',  'د.ك', 'KWD', 3, false],
            ['QAR', 'ريال قطري',  'Qatari Riyal',   'ر.ق', 'QAR', 2, false],
            ['JOD', 'دينار أردني', 'Jordanian Dinar', 'د.أ', 'JOD', 3, false],
            ['MAD', 'درهم مغربي', 'Moroccan Dirham', 'د.م', 'MAD', 2, false],
            ['USD', 'دولار أمريكي', 'US Dollar',     '$',   '$',   2, false],
            ['EUR', 'يورو',       'Euro',           '€',   '€',   2, false],
        ];

        foreach ($rows as $i => [$code, $ar, $en, $symAr, $symEn, $decimals, $isBase]) {
            DB::table('currencies')->updateOrInsert(['code' => $code], [
                'name' => json_encode(['ar' => $ar, 'en' => $en], JSON_UNESCAPED_UNICODE),
                'symbol' => json_encode(['ar' => $symAr, 'en' => $symEn], JSON_UNESCAPED_UNICODE),
                'decimals' => $decimals,
                'position' => 'after',
                'is_base' => $isBase,
                'is_active' => true,
                'position_order' => $i,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }
}
