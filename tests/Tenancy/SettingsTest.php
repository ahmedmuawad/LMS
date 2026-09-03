<?php

declare(strict_types=1);

use App\Core\Settings\SettingsRepository;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

// وثيقة 05 — طبقة الإعدادات، معزولة لكل مشترك.

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(CountrySeeder::class);
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

it('reads the defaults seeded by the platform mode', function () {
    $tenant = provision(['platform_mode' => 'marketplace']);

    $tenant->run(function (): void {
        expect(setting('lms.instructor_signup'))->toBeTrue()
            ->and(setting('lms.require_course_approval'))->toBeTrue()
            ->and(setting('commerce.commission_rate'))->toBe(70);
    });

    tenancy()->end();
});

it('falls back to the given default for a missing key', function () {
    $tenant = provision();

    $tenant->run(function (): void {
        expect(setting('lms.passing_percentage', 60))->toBe(60)
            ->and(setting()->has('lms.passing_percentage'))->toBeFalse();
    });

    tenancy()->end();
});

it('writes and reads back through the cache', function () {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('lms.passing_percentage', 75);

        expect(setting('lms.passing_percentage'))->toBe(75);

        // نسخة جديدة من المستودع تقرأ نفس القيمة من الكاش/القاعدة
        expect(app(SettingsRepository::class)->get('lms.passing_percentage'))->toBe(75);
    });

    tenancy()->end();
});

it('keeps settings isolated between tenants', function () {
    $a = provision(['name' => 'أ', 'owner_email' => 'sa@example.test']);
    $b = provision(['name' => 'ب', 'owner_email' => 'sb@example.test']);

    $a->run(fn () => setting()->set('lms.passing_percentage', 90));
    tenancy()->end();

    $b->run(fn () => expect(setting('lms.passing_percentage', 60))->toBe(60));
    tenancy()->end();

    $a->run(fn () => expect(setting('lms.passing_percentage'))->toBe(90));
    tenancy()->end();
});

it('returns the right language for a translatable setting', function () {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('seo.default_description', [
            'ar' => 'منصة تعليمية عربية',
            'en' => 'An Arabic learning platform',
        ], translatable: true);

        app()->setLocale('ar');
        expect(setting()->translated('seo.default_description'))->toBe('منصة تعليمية عربية');

        app()->setLocale('en');
        expect(setting()->translated('seo.default_description'))->toBe('An Arabic learning platform');

        // لغة غير مترجمة تسقط إلى الافتراضية
        expect(setting()->translated('seo.default_description', 'fr'))->toBe('منصة تعليمية عربية');
    });

    app()->setLocale('ar');
    tenancy()->end();
});

it('encrypts secrets at rest', function () {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('payments.paymob_api_key', 'sk_live_secret_123', encrypted: true);

        // القيمة المخزّنة ليست النص الصريح
        $stored = DB::table('settings')->where('group', 'payments')->value('value');
        expect($stored)->not->toContain('sk_live_secret_123');

        // والقراءة ترجع النص الصحيح
        expect(setting('payments.paymob_api_key'))->toBe('sk_live_secret_123');
    });

    tenancy()->end();
});

it('reads a whole group with short keys', function () {
    $tenant = provision(['platform_mode' => 'center', 'plan_key' => 'center']);

    $tenant->run(function (): void {
        $center = setting()->group('center');

        expect($center)->toHaveKey('enabled')
            ->and($center['enabled'])->toBeTrue()
            ->and($center['week_start'])->toBe(6);
    });

    tenancy()->end();
});

it('rejects a malformed setting path', function () {
    $tenant = provision();
    $tenant->run(fn () => setting()->set('no_group', 1));
})->throws(InvalidArgumentException::class);
