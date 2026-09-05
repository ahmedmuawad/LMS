<?php

declare(strict_types=1);

use App\Core\Commerce\TaxRates;
use App\Core\Settings\SettingsRepository;
use App\Core\Support\Money;
use App\Modules\Commerce\Models\ShippingZone;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;

/*
| الضريبة لكل دولة، ومناطق الشحن.
|
| والاختبار هنا لأن الخطأ يمسّ المال لا العرض: نسبةٌ خاطئة تعني
| مالاً يُحصَّل باسم الضريبة ولا يُورَّد — أو يُورَّد ناقصاً. وكلاهما
| مساءلةٌ على المشترك لا علينا، وهو لا يعرف أن منصّته حسبتها له خطأً.
*/

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);

    $this->tenant = provision();

    // الدول مرجعٌ مركزي: تُكتب خارج سياق المشترك
    DB::connection(config('tenancy.database.central_connection'))
        ->table('countries')->where('code', 'SA')
        ->update(['tax_enabled' => true, 'tax_rate' => 15.0]);

    DB::connection(config('tenancy.database.central_connection'))
        ->table('countries')->where('code', 'AE')
        ->update(['tax_enabled' => true, 'tax_rate' => 5.0]);

    DB::connection(config('tenancy.database.central_connection'))
        ->table('countries')->where('code', 'KW')
        ->update(['tax_enabled' => false, 'tax_rate' => 0]);
});

it('reads the rate from the buyer country, not one flat number', function () {
    $this->tenant->run(function (): void {
        app(SettingsRepository::class)->set('currency.default_rate', 14);
        app(SettingsRepository::class)->flush();

        $rates = app(TaxRates::class);

        expect($rates->rateFor('SA'))->toBe(15.0)
            ->and($rates->rateFor('AE'))->toBe(5.0);
    });
});

it('treats a country with tax switched off as zero, not as the default', function () {
    $this->tenant->run(function (): void {
        app(SettingsRepository::class)->set('currency.default_rate', 14);
        app(SettingsRepository::class)->flush();

        /*
         | الكويت بلا ضريبةٍ على الخدمات، وإطفاؤها قرارٌ صريح.
         |
         | وسقوطُه إلى الافتراضي يجعل المشترك يحصّل ضريبةً لا وجود لها.
         */
        expect(app(TaxRates::class)->rateFor('KW'))->toBe(0.0);
    });
});

it('falls back to the default rate for a country it does not know', function () {
    $this->tenant->run(function (): void {
        app(SettingsRepository::class)->set('currency.default_rate', 14);
        app(SettingsRepository::class)->flush();

        // وصفرٌ صامت أسوأ: يبيع بلا ضريبةٍ ولا يُلاحَظ
        expect(app(TaxRates::class)->rateFor('ZZ'))->toBe(14.0)
            ->and(app(TaxRates::class)->rateFor(null))->toBe(14.0);
    });
});

it('picks the zone whose country list contains the buyer', function () {
    $this->tenant->run(function (): void {
        ShippingZone::create([
            'name' => ['ar' => 'مصر'], 'countries' => ['EG'],
            'rate_minor' => 5000, 'free_over_minor' => 0, 'sort_order' => 1,
        ]);

        ShippingZone::create([
            'name' => ['ar' => 'الخليج'], 'countries' => ['SA', 'AE', 'KW'],
            'rate_minor' => 15000, 'free_over_minor' => 0, 'sort_order' => 2,
        ]);

        expect(ShippingZone::forCountry('EG')?->rate_minor)->toBe(5000)
            ->and(ShippingZone::forCountry('AE')?->rate_minor)->toBe(15000);
    });
});

it('lets a star zone catch the rest of the world, but only last', function () {
    $this->tenant->run(function (): void {
        ShippingZone::create([
            'name' => ['ar' => 'بقيّة العالم'], 'countries' => ['*'],
            'rate_minor' => 30000, 'sort_order' => 99,
        ]);

        ShippingZone::create([
            'name' => ['ar' => 'مصر'], 'countries' => ['EG'],
            'rate_minor' => 5000, 'sort_order' => 1,
        ]);

        /*
         | الترتيب يحسم التعارض.
         |
         | ومنطقةٌ تحمل `*` بترتيبٍ صغير تبتلع كل ما بعدها، فلا
         | تُطبَّق منطقةٌ واحدة غيرها.
         */
        expect(ShippingZone::forCountry('EG')?->rate_minor)->toBe(5000)
            ->and(ShippingZone::forCountry('JP')?->rate_minor)->toBe(30000);
    });
});

it('drops the shipping cost once the order passes the zone free threshold', function () {
    $this->tenant->run(function (): void {
        $zone = ShippingZone::create([
            'name' => ['ar' => 'مصر'], 'countries' => ['EG'],
            'rate_minor' => 5000, 'free_over_minor' => 100000,
        ]);

        expect($zone->costFor(Money::fromMinor(99999, 'EGP'))->minor)->toBe(5000)
            ->and($zone->costFor(Money::fromMinor(100000, 'EGP'))->minor)->toBe(0);
    });
});

it('ignores an inactive zone so a paused rate cannot bill anyone', function () {
    $this->tenant->run(function (): void {
        ShippingZone::create([
            'name' => ['ar' => 'مصر'], 'countries' => ['EG'],
            'rate_minor' => 5000, 'is_active' => false,
        ]);

        expect(ShippingZone::forCountry('EG'))->toBeNull();
    });
});
