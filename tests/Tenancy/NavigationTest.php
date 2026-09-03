<?php

declare(strict_types=1);

use App\Core\Admin\Navigation;
use App\Core\Tenancy\Actions\ApplyPlatformMode;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

// ADR-010 / ADR-011 — ما لا يخصّ نمطه يُخفى، وما تمنعه باقته يُعرض مقفولاً.

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(CountrySeeder::class);
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

function navFor(string $mode, string $plan): array
{
    $tenant = provision([
        'platform_mode' => $mode,
        'plan_key' => $plan,
        'owner_email' => $mode.'-'.$plan.'@example.test',
    ]);

    return $tenant->run(function (): array {
        $items = [];

        foreach (app(Navigation::class)->groups() as $group) {
            foreach ($group['items'] as $item) {
                $items[$item['key']] = $item;
            }
        }

        return $items;
    });
}

it('hides what does not belong to the platform mode', function () {
    $solo = navFor('solo', 'growth');

    expect($solo)->toHaveKeys(['courses', 'quizzes', 'orders', 'posts'])
        ->and($solo)->not->toHaveKey('groups')        // السنتر
        ->and($solo)->not->toHaveKey('attendance')
        ->and($solo)->not->toHaveKey('guardians')
        ->and($solo)->not->toHaveKey('instructors');  // تعدّد المدرّسين

    tenancy()->end();
});

it('shows the centre group only in centre mode', function () {
    $center = navFor('center', 'center');

    expect($center)->toHaveKeys(['groups', 'schedule', 'attendance', 'fees', 'guardians', 'inventory'])
        ->and($center)->not->toHaveKey('page-builder')   // ليس ضمن موديولات نمط السنتر
        ->and($center)->not->toHaveKey('affiliates');

    tenancy()->end();
});

it('shows a feature outside the plan as locked, not hidden', function () {
    // الميزة المخفية لا تُباع: تظهر بقفل وبلا رابط
    $market = navFor('marketplace', 'growth');

    expect($market)->toHaveKey('standards')
        ->and($market['standards']['locked'])->toBeTrue()
        ->and($market['standards']['url'])->toBeNull()
        ->and($market['standards']['feature'])->toBe('scorm');

    tenancy()->end();
});

it('unlocks the same item on a plan that grants it', function () {
    $pro = navFor('hybrid', 'professional');

    expect($pro['standards']['locked'])->toBeFalse()
        ->and($pro['standards']['url'])->not->toBeNull()
        ->and($pro['services']['locked'])->toBeFalse();

    tenancy()->end();
});

it('reflects a mode change in the navigation immediately', function () {
    $tenant = provision(['platform_mode' => 'solo', 'plan_key' => 'professional']);

    $before = $tenant->run(fn () => collect(app(Navigation::class)->groups())
        ->flatMap(fn ($g) => array_column($g['items'], 'key'))->all());
    expect($before)->not->toContain('attendance');
    tenancy()->end();

    app(ApplyPlatformMode::class)->handle($tenant->refresh(), 'hybrid');

    $after = $tenant->run(fn () => collect(app(Navigation::class)->groups())
        ->flatMap(fn ($g) => array_column($g['items'], 'key'))->all());
    expect($after)->toContain('attendance');
    tenancy()->end();
});

it('renders the locked item with a lock and an explanation', function () {
    $tenant = provision(['platform_mode' => 'marketplace', 'plan_key' => 'growth']);
    actingAsOwner($tenant);

    $this->get('http://'.$tenant->domains->first()->domain.'/admin/users')
        ->assertOk()
        ->assertSee('SCORM و H5P', false)
        ->assertSee('غير متاح في باقتك الحالية', false)
        ->assertSee('cursor-not-allowed', false);

    tenancy()->end();
});

it('shows the trial banner while on trial', function () {
    $tenant = provision(['plan_key' => 'growth']);
    actingAsOwner($tenant);

    $this->get('http://'.$tenant->domains->first()->domain.'/admin/users')
        ->assertOk()
        ->assertSee('تجربتك المجانية', false);

    tenancy()->end();
});

it('warns a past-due tenant without shutting the panel', function () {
    $tenant = provision(['plan_key' => 'growth']);
    $tenant->forceFill(['status' => 'past_due', 'trial_ends_at' => null])->save();
    actingAsOwner($tenant);

    $this->get('http://'.$tenant->domains->first()->domain.'/admin/users')
        ->assertOk()
        ->assertSee('تعذّر تحصيل اشتراكك', false);

    tenancy()->end();
});

it('never renders an empty navigation group', function () {
    $tenant = provision(['platform_mode' => 'solo', 'plan_key' => 'starter']);

    $groups = $tenant->run(fn () => app(Navigation::class)->groups());

    foreach ($groups as $group) {
        expect($group['items'])->not->toBeEmpty();
    }

    tenancy()->end();
});
