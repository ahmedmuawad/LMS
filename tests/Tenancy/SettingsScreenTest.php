<?php

declare(strict_types=1);

use App\Core\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

it('sends the settings root to the first visible group', function () {
    $tenant = provision();
    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/settings')->assertRedirect(url('/admin/settings/general'));
});

it('renders every visible settings screen', function () {
    $tenant = provision(['platform_mode' => 'hybrid']);
    actingAsOwner($tenant);

    foreach (app(SettingsRegistry::class)->visible() as $group) {
        tenantGet($tenant, '/admin/settings/'.$group->key())
            ->assertOk()
            ->assertSee($group->label());
    }
})->skip(fn () => false);

it('hides a screen whose module is switched off for this mode', function () {
    $tenant = provision(['platform_mode' => 'center']);

    $keys = $tenant->run(fn (): array => array_map(
        fn ($group) => $group->key(),
        app(SettingsRegistry::class)->visible(),
    ));

    // نمط السنتر لا يفعّل موديول المدونة، فلا شاشة إعدادات لها
    expect($keys)->toContain('general')->toContain('commerce')->not->toContain('content');
});

it('refuses a hidden screen exactly as it refuses a missing one', function () {
    $tenant = provision(['platform_mode' => 'center']);
    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/settings/content')->assertNotFound();
    tenantGet($tenant, '/admin/settings/does-not-exist')->assertNotFound();
});

it('saves a value and reads it back', function () {
    $tenant = provision();
    actingAsOwner($tenant);

    tenantPut($tenant, '/admin/settings/lms', [
        'passing_percentage' => 75,
        'quiz_attempts' => 5,
    ])->assertRedirect(url('/admin/settings/lms'));

    $tenant->run(function (): void {
        expect(setting('lms.passing_percentage'))->toBe(75)
            ->and(setting('lms.quiz_attempts'))->toBe(5);
    });
});

it('rejects a value outside its range without saving anything', function () {
    $tenant = provision();
    actingAsOwner($tenant);

    tenantPut($tenant, '/admin/settings/lms', ['passing_percentage' => 500])
        ->assertSessionHasErrors('passing_percentage');

    $tenant->run(fn () => expect(setting('lms.passing_percentage'))->toBeNull());
});

it('names the field in Arabic when it complains', function () {
    $tenant = provision();
    actingAsOwner($tenant);

    tenantPut($tenant, '/admin/settings/appearance', ['primary' => 'not-a-color'])
        ->assertSessionHasErrors(['primary' => 'صيغة اللون الأساسي غير صحيحة.']);
});

it('stores a secret encrypted and never sends it back to the browser', function () {
    $tenant = provision();
    actingAsOwner($tenant);

    tenantPut($tenant, '/admin/settings/integrations', ['video_api_key' => 'super-secret-key']);

    $tenant->run(function (): void {
        $row = DB::table('settings')->where('group', 'integrations')->where('key', 'video_api_key')->first();

        expect($row->is_encrypted)->toBeTruthy()
            ->and(json_decode($row->value, true))->not->toBe('super-secret-key')
            ->and(Crypt::decryptString(json_decode($row->value, true)))->toBe('super-secret-key');
    });

    tenantGet($tenant, '/admin/settings/integrations')->assertOk()->assertDontSee('super-secret-key');
});

it('keeps a stored secret when the field is submitted empty', function () {
    $tenant = provision();
    actingAsOwner($tenant);

    tenantPut($tenant, '/admin/settings/integrations', ['video_api_key' => 'first-key']);
    tenantPut($tenant, '/admin/settings/integrations', ['video_api_key' => '']);

    $tenant->run(fn () => expect(setting('integrations.video_api_key'))->toBe('first-key'));
});

it('keeps two languages in one field', function () {
    $tenant = provision();
    actingAsOwner($tenant);

    tenantPut($tenant, '/admin/settings/general', [
        'site_name' => ['ar' => 'أكاديمية النور', 'en' => 'Al-Noor Academy'],
    ]);

    $tenant->run(function (): void {
        expect(setting('general.site_name'))->toBe(['ar' => 'أكاديمية النور', 'en' => 'Al-Noor Academy'])
            ->and(setting()->translated('general.site_name', 'en'))->toBe('Al-Noor Academy');
    });
});

it('falls back to the default language when a translation is missing', function () {
    $tenant = provision();
    actingAsOwner($tenant);

    tenantPut($tenant, '/admin/settings/general', ['site_name' => ['ar' => 'أكاديمية النور', 'en' => '']]);

    $tenant->run(fn () => expect(setting()->translated('general.site_name', 'en'))->toBe('أكاديمية النور'));
});

it('offers every payment gateway its own settings block', function () {
    $tenant = provision();
    actingAsOwner($tenant);

    $response = tenantGet($tenant, '/admin/settings/payments')->assertOk();

    foreach (config('payments.gateways') as $gateway) {
        $response->assertSee($gateway['label']);
    }
});

it('keeps one tenant settings out of another', function () {
    $first = provision(['name' => 'الأولى', 'slug' => 'first-'.uniqid()]);
    $second = provision(['name' => 'الثانية', 'slug' => 'second-'.uniqid(), 'owner_email' => 'other@example.test']);

    $first->run(fn () => setting()->set('general.phone', '01000000001'));
    $second->run(fn () => setting()->set('general.phone', '01000000002'));

    expect($first->run(fn () => setting('general.phone')))->toBe('01000000001')
        ->and($second->run(fn () => setting('general.phone')))->toBe('01000000002');
});
