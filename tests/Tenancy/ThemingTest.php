<?php

declare(strict_types=1);

use App\Core\Theming\ThemeManager;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

// ترتيب البحث: ثيم المشترك ← الموديول ← النواة

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(CountrySeeder::class);
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

it('discovers every theme on disk', function () {
    expect(array_keys(app(ThemeManager::class)->all()))
        ->toContain('solo-academy', 'marketplace', 'center', 'hybrid');
});

it('offers only the themes that fit a platform mode', function () {
    $themes = app(ThemeManager::class);

    expect(array_keys($themes->forMode('center')))->toBe(['center', 'hybrid'])
        ->and(array_keys($themes->forMode('solo')))->toContain('solo-academy')
        ->and(array_keys($themes->forMode('solo')))->not->toContain('center');
});

it('falls back to the default theme when asked for one that does not exist', function () {
    $themes = app(ThemeManager::class);
    $themes->use('theme-that-does-not-exist');

    expect($themes->active())->toBe($themes->default());
});

it('lets a theme override a core view without copying the whole system', function () {
    // نمط السنتر يختار ثيم center، وله عرض بديل للصفحة الرئيسية
    $center = provision([
        'name' => 'سنتر النجاح',
        'owner_email' => 'center@example.test',
        'platform_mode' => 'center',
        'plan_key' => 'center',
    ]);

    $this->get('http://'.$center->domains->first()->domain.'/')
        ->assertOk()
        ->assertSee('جدول الحصص والحضور والأقساط', false);   // من ثيم السنتر

    tenancy()->end();

    // ونمط المدرّس الفردي يقع على عرض النواة لأن ثيمه لا يعرّف بديلاً
    $solo = provision([
        'name' => 'أكاديمية فردية',
        'owner_email' => 'solo@example.test',
        'platform_mode' => 'solo',
    ]);

    $this->get('http://'.$solo->domains->first()->domain.'/')
        ->assertOk()
        ->assertSee('ابدأ بإضافة أول كورس', false);          // من النواة

    tenancy()->end();
});

it('reads the theme manifest', function () {
    $manifest = app(ThemeManager::class)->manifest('center');

    expect($manifest['key'])->toBe('center')
        ->and($manifest['name']['ar'])->toBe('سنتر تعليمي')
        ->and($manifest['supports'])->toContain('rtl', 'dark');
});
