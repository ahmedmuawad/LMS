<?php

declare(strict_types=1);

use App\Core\Onboarding\OnboardingWizard;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;

// ADR-010 — الاختيار في المعالج يُترجم إلى حالة فعلية، لا إلى تفضيل مخزّن.

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(CountrySeeder::class);
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);

    $this->tenant = provision(['name' => 'أكاديمية جديدة', 'owner_email' => 'ob@example.test'], onboarded: false);
    $this->base = 'http://'.$this->tenant->domains->first()->domain;
    actingAsOwner($this->tenant);
});

afterEach(fn () => tenancy()->initialized && tenancy()->end());

it('sends an un-onboarded tenant from the panel to the wizard', function () {
    $this->get($this->base.'/admin/users')
        ->assertRedirect($this->base.'/onboarding/mode');
});

it('keeps the public site working while onboarding is incomplete', function () {
    // لا نعاقب طلابه بأن مالك المنصة لم يُكمل التهيئة
    $this->get($this->base.'/')->assertOk();
});

it('refuses to jump ahead to a step not yet reached', function () {
    $this->get($this->base.'/onboarding/mode')->assertOk();
    $this->get($this->base.'/onboarding/locale')->assertRedirect($this->base.'/onboarding/mode');
});

it('walks the whole wizard and lands in the panel', function () {
    $this->post($this->base.'/onboarding/mode', ['mode' => 'center', 'center_enabled' => '1'])
        ->assertRedirect($this->base.'/onboarding/delivery');

    $this->post($this->base.'/onboarding/delivery', ['delivery' => 'blended'])
        ->assertRedirect($this->base.'/onboarding/identity');

    $this->post($this->base.'/onboarding/identity', [
        'name' => 'سنتر النجاح', 'tagline' => 'تفوّق من أول حصة',
        'theme' => 'center', 'primary_color' => '#8A1538',
    ])->assertRedirect($this->base.'/onboarding/locale');

    $this->post($this->base.'/onboarding/locale', [
        'locale' => 'ar', 'country' => 'SA', 'currency' => 'SAR', 'numerals' => 'hindi',
    ])->assertRedirect($this->base.'/onboarding/done');

    $this->post($this->base.'/onboarding/finish')->assertRedirect($this->base.'/admin/dashboard');

    // الاختيارات صارت حالة فعلية
    $tenant = $this->tenant->refresh();

    expect($tenant->platform_mode)->toBe('center')
        ->and($tenant->delivery_mode)->toBe('blended')
        ->and($tenant->center_enabled)->toBeTrue()
        ->and($tenant->name)->toBe('سنتر النجاح')
        ->and($tenant->theme)->toBe('center')
        ->and($tenant->country)->toBe('SA')
        ->and($tenant->currency)->toBe('SAR')
        ->and($tenant->timezone)->toBe('Asia/Riyadh');

    $tenant->run(function (): void {
        expect(app(OnboardingWizard::class)->isComplete())->toBeTrue()
            ->and(setting('appearance.primary_color'))->toBe('#8A1538')
            ->and(setting('appearance.numerals'))->toBe('hindi')
            ->and(setting('site.tagline'))->toBe('تفوّق من أول حصة');

        // الموديولات التي فعّلها النمط المختار
        $modules = DB::table('modules')->where('enabled', true)->pluck('key')->all();
        expect($modules)->toContain('center', 'attendance', 'parent-portal', 'live')
            ->and($modules)->not->toContain('page-builder');
    });
});

it('opens the panel once onboarding is complete', function () {
    $this->tenant->run(fn () => app(OnboardingWizard::class)->complete());

    $this->get($this->base.'/admin/users')->assertOk();
});

it('sends a completed tenant away from the wizard', function () {
    $this->tenant->run(fn () => app(OnboardingWizard::class)->complete());

    $this->get($this->base.'/onboarding/mode')->assertRedirect($this->base.'/admin/dashboard');
});

it('rejects a mode that does not exist', function () {
    $this->from($this->base.'/onboarding/mode')
        ->post($this->base.'/onboarding/mode', ['mode' => 'god-mode'])
        ->assertSessionHasErrors('mode');
});

it('rejects a colour that is not a hex value', function () {
    $this->tenant->run(fn () => setting()->set('onboarding.step', 'identity'));

    $this->from($this->base.'/onboarding/identity')
        ->post($this->base.'/onboarding/identity', [
            'name' => 'اسم', 'theme' => 'center', 'primary_color' => 'red; drop table users',
        ])->assertSessionHasErrors('primary_color');
});

it('rejects a country that is not in the central table', function () {
    $this->tenant->run(fn () => setting()->set('onboarding.step', 'locale'));

    $this->from($this->base.'/onboarding/locale')
        ->post($this->base.'/onboarding/locale', [
            'locale' => 'ar', 'country' => 'ZZ', 'currency' => 'EGP', 'numerals' => 'arabic',
        ])->assertSessionHasErrors('country');
});

it('does not lose progress when the user goes back to edit a step', function () {
    $this->post($this->base.'/onboarding/mode', ['mode' => 'solo']);
    $this->post($this->base.'/onboarding/delivery', ['delivery' => 'recorded']);

    // العودة لتعديل الخطوة الأولى لا تعيده إلى البداية
    $this->post($this->base.'/onboarding/mode', ['mode' => 'marketplace'])
        ->assertRedirect($this->base.'/onboarding/identity');

    expect($this->tenant->refresh()->platform_mode)->toBe('marketplace');
});

it('offers only the themes that suit the chosen mode', function () {
    $this->post($this->base.'/onboarding/mode', ['mode' => 'center']);
    $this->post($this->base.'/onboarding/delivery', ['delivery' => 'recorded']);

    $this->get($this->base.'/onboarding/identity')
        ->assertOk()
        ->assertSee('value="center"', false)
        ->assertDontSee('value="marketplace"', false);
});

it('hides modules of the previous mode without deleting their data', function () {
    $this->post($this->base.'/onboarding/mode', ['mode' => 'solo']);
    $this->post($this->base.'/onboarding/delivery', ['delivery' => 'recorded']);

    $this->tenant->run(fn () => expect(DB::table('modules')->where('key', 'page-builder')->value('enabled'))->toBe(1));

    // التحويل إلى نمط السنتر يخفي محرّر الصفحات لكن لا يمحو صفّه
    $this->post($this->base.'/onboarding/mode', ['mode' => 'center']);
    $this->post($this->base.'/onboarding/delivery', ['delivery' => 'recorded']);

    $this->tenant->run(function (): void {
        $row = DB::table('modules')->where('key', 'page-builder')->first();
        expect($row)->not->toBeNull()
            ->and($row->enabled)->toBe(0)
            ->and(DB::table('modules')->where('key', 'attendance')->value('enabled'))->toBe(1);
    });
});
