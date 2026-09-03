<?php

declare(strict_types=1);

use App\Core\Tenancy\Actions\ApplyPlatformMode;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;

// ADR-009 / ADR-010 — التجهيز الآلي وتطبيق نمط المنصة.

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(CountrySeeder::class);
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

it('provisions a working tenant end to end', function () {
    $tenant = provision();

    expect($tenant->status)->toBe('trialing')
        ->and($tenant->provisioned_at)->not->toBeNull()
        ->and($tenant->provision_error)->toBeNull()
        ->and($tenant->domains)->toHaveCount(1)
        ->and($tenant->domains->first()->is_primary)->toBeTrue()
        ->and($tenant->domains->first()->type)->toBe('subdomain');
});

it('derives currency and timezone from the country', function () {
    $saudi = provision(['country' => 'SA', 'owner_email' => 'sa@example.test']);

    expect($saudi->currency)->toBe('SAR')
        ->and($saudi->timezone)->toBe('Asia/Riyadh');
});

it('creates the owner account inside the tenant database', function () {
    $tenant = provision();

    $tenant->run(function (): void {
        expect(DB::table('users')->count())->toBe(1)
            ->and(DB::table('users')->value('email'))->toBe('owner@example.test');
    });
});

it('keeps tenant data isolated in separate databases', function () {
    $a = provision(['name' => 'أكاديمية أ', 'owner_email' => 'a@example.test']);
    $b = provision(['name' => 'أكاديمية ب', 'owner_email' => 'b@example.test']);

    $a->run(fn () => DB::table('users')->insert([
        'name' => 'طالب في أ', 'email' => 'student@a.test', 'created_at' => now(), 'updated_at' => now(),
    ]));

    $a->run(fn () => expect(DB::table('users')->count())->toBe(2));
    $b->run(fn () => expect(DB::table('users')->count())->toBe(1));   // لم يتسرّب شيء
});

it('starts the trial from the plan', function () {
    $tenant = provision(['plan_key' => 'starter']);

    expect($tenant->onTrial())->toBeTrue()
        ->and($tenant->trialDaysLeft())->toBe(14);
});

it('gives every mode its own module set', function () {
    $solo = provision(['platform_mode' => 'solo',        'owner_email' => 's@example.test']);
    $center = provision(['platform_mode' => 'center',      'owner_email' => 'c@example.test', 'plan_key' => 'center']);

    $soloModules = $solo->run(fn () => DB::table('modules')->pluck('key')->all());
    $centerModules = $center->run(fn () => DB::table('modules')->pluck('key')->all());

    expect($soloModules)->not->toContain('center')
        ->and($soloModules)->not->toContain('attendance')
        ->and($centerModules)->toContain('center')
        ->and($centerModules)->toContain('attendance')
        ->and($centerModules)->toContain('parent-portal')
        ->and($centerModules)->not->toContain('page-builder');
});

it('adds live modules only for live or blended delivery', function () {
    $recorded = provision(['delivery_mode' => 'recorded', 'owner_email' => 'r@example.test']);
    $blended = provision(['delivery_mode' => 'blended',  'owner_email' => 'b2@example.test']);

    expect($recorded->run(fn () => DB::table('modules')->pluck('key')->all()))->not->toContain('live')
        ->and($blended->run(fn () => DB::table('modules')->pluck('key')->all()))->toContain('live');
});

it('lets a solo instructor still run a small center', function () {
    $tenant = provision(['platform_mode' => 'solo', 'center_enabled' => true]);

    expect($tenant->managesCenter())->toBeTrue()
        ->and($tenant->run(fn () => DB::table('modules')->pluck('key')->all()))->toContain('attendance');
});

it('seeds the default settings of the chosen mode', function () {
    $tenant = provision(['platform_mode' => 'marketplace']);

    $tenant->run(function (): void {
        $value = DB::table('settings')->where('group', 'lms')->where('key', 'instructor_signup')->value('value');
        expect(json_decode((string) $value, true))->toBeTrue();
    });
});

it('changes mode later without losing data', function () {
    $tenant = provision(['platform_mode' => 'solo']);

    $tenant->run(fn () => DB::table('users')->insert([
        'name' => 'طالب قديم', 'email' => 'old@student.test', 'created_at' => now(), 'updated_at' => now(),
    ]));

    app(ApplyPlatformMode::class)->handle($tenant->refresh(), 'hybrid');

    expect($tenant->refresh()->platform_mode)->toBe('hybrid')
        ->and($tenant->run(fn () => DB::table('users')->count()))->toBe(2)
        ->and($tenant->run(fn () => DB::table('modules')->pluck('key')->all()))->toContain('center');
});

it('rejects an unknown platform mode', function () {
    app(ApplyPlatformMode::class)->handle(provision(), 'nonsense');
})->throws(InvalidArgumentException::class);

it('generates unique slugs', function () {
    $a = provision(['name' => 'أكاديمية النور', 'owner_email' => 'n1@example.test']);
    $b = provision(['name' => 'أكاديمية النور', 'owner_email' => 'n2@example.test']);

    expect($b->slug)->not->toBe($a->slug);
});

it('serves each tenant site on its own domain', function () {
    $tenant = provision(['name' => 'أكاديمية النطاق', 'owner_email' => 'dom@example.test']);
    $domain = $tenant->domains->first()->domain;

    $this->get('http://'.$domain.'/')
        ->assertOk()
        ->assertSee('أكاديمية النطاق', false);

    tenancy()->end();
});

it('refuses tenant routes on the central domain', function () {
    provision(['owner_email' => 'central@example.test']);

    // "/" على النطاق المركزي هو موقعنا نحن، لا موقع أي مشترك
    $this->get('http://localhost/')->assertOk()->assertDontSee('أكاديمية الاختبار', false);

    tenancy()->end();
});

it('does not leak one tenant site to another domain', function () {
    $a = provision(['name' => 'أكاديمية ألف', 'owner_email' => 'aa@example.test']);
    $b = provision(['name' => 'أكاديمية باء', 'owner_email' => 'bb@example.test']);

    $this->get('http://'.$a->domains->first()->domain.'/')
        ->assertSee('أكاديمية ألف', false)
        ->assertDontSee('أكاديمية باء', false);

    tenancy()->end();   // كل طلب حقيقي يبدأ بسياق نظيف

    $this->get('http://'.$b->domains->first()->domain.'/')
        ->assertSee('أكاديمية باء', false)
        ->assertDontSee('أكاديمية ألف', false);

    tenancy()->end();
});
