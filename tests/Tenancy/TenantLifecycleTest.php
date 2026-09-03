<?php

declare(strict_types=1);

use App\Core\Audit\Models\AuditLog;
use App\Core\Entitlements\Models\Plan;
use App\Core\Tenancy\Actions\ChangeTenantPlan;
use App\Core\Tenancy\Actions\ChangeTenantStatus;
use App\Core\Tenancy\Models\Tenant;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
    actingAsSuperAdmin();
});

it('moves a tenant through an allowed transition', function () {
    $tenant = makeTenant(attributes: ['status' => 'active']);

    app(ChangeTenantStatus::class)->handle($tenant, 'suspended', 'تعثّر في السداد');

    expect($tenant->fresh()->status)->toBe('suspended')
        ->and($tenant->fresh()->suspended_at)->not->toBeNull();
});

it('refuses a transition that makes no business sense', function () {
    $tenant = makeTenant(attributes: ['status' => 'archived']);

    expect(fn () => app(ChangeTenantStatus::class)->handle($tenant, 'past_due'))
        ->toThrow(InvalidArgumentException::class);
});

it('clears the suspension stamp when a tenant comes back', function () {
    $tenant = makeTenant(attributes: ['status' => 'active']);

    app(ChangeTenantStatus::class)->handle($tenant, 'suspended');
    app(ChangeTenantStatus::class)->handle($tenant->fresh(), 'active');

    expect($tenant->fresh()->suspended_at)->toBeNull();
});

it('keeps a suspended tenant site available to its students', function () {
    $tenant = makeTenant(attributes: ['status' => 'suspended']);

    expect($tenant->isPubliclyAvailable())->toBeTrue()
        ->and($tenant->canAccessDashboard())->toBeFalse();
});

it('records who changed a status and why', function () {
    $tenant = makeTenant(attributes: ['status' => 'active']);

    app(ChangeTenantStatus::class)->handle($tenant, 'past_due', 'فشل التحصيل');

    $entry = AuditLog::where('action', 'tenant.status_changed')->firstOrFail();

    expect($entry->tenant_id)->toBe($tenant->id)
        ->and($entry->actor_name)->toBe('عضو الفريق')
        ->and($entry->meta['from'])->toBe('active')
        ->and($entry->meta['to'])->toBe('past_due')
        ->and($entry->meta['reason'])->toBe('فشل التحصيل');
});

it('changes a plan and stops serving the old limits', function () {
    $tenant = provision();

    Plan::create([
        'key' => 'tiny', 'name' => ['ar' => 'صغيرة'], 'prices' => ['EGP' => 1000],
        'is_active' => true, 'is_public' => true,
    ]);
    Plan::find('tiny')->features()->create(['feature_key' => 'students', 'value' => '5']);

    $before = $tenant->limitOf('students');

    app(ChangeTenantPlan::class)->handle($tenant, 'tiny');

    expect(Tenant::find($tenant->id)->limitOf('students'))->toBe(5)
        ->and(Tenant::find($tenant->id)->limitOf('students'))->not->toBe($before);
});

it('refuses a plan that does not support the tenant platform mode', function () {
    $tenant = makeTenant(attributes: ['platform_mode' => 'solo']);

    Plan::create([
        'key' => 'centers-only', 'name' => ['ar' => 'للسناتر'], 'prices' => ['EGP' => 9900],
        'modes' => ['center'], 'is_active' => true, 'is_public' => true,
    ]);

    expect(fn () => app(ChangeTenantPlan::class)->handle($tenant, 'centers-only'))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a plan that is switched off', function () {
    $tenant = makeTenant();

    Plan::create([
        'key' => 'retired', 'name' => ['ar' => 'ملغاة'], 'prices' => ['EGP' => 1000],
        'is_active' => false, 'is_public' => false,
    ]);

    expect(fn () => app(ChangeTenantPlan::class)->handle($tenant, 'retired'))
        ->toThrow(InvalidArgumentException::class);
});
