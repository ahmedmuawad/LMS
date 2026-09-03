<?php

declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

function visitTenant(Tenant $tenant, string $path = '/admin/dashboard')
{
    $domain = $tenant->domains->firstWhere('is_primary', true)?->domain
        ?? $tenant->domains()->first()->domain;

    return test()->get('http://'.$domain.$path);
}

it('opens the dashboard for the owner', function () {
    $tenant = provision();
    actingAsOwner($tenant);

    visitTenant($tenant)
        ->assertOk()
        ->assertSee($tenant->name)
        ->assertSee('استهلاكك من الباقة');
});

it('sends a guest to the login screen, not to the dashboard', function () {
    $tenant = provision();

    visitTenant($tenant)->assertRedirectContains('/login');
});

it('keeps a student out of the dashboard without pretending the session expired', function () {
    $tenant = provision();

    $student = $tenant->run(fn () => User::create([
        'name' => 'طالبة', 'email' => 'student@example.test',
        'password' => 'secret-password', 'role' => 'student', 'status' => 'active',
    ]));

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    visitTenant($tenant)->assertForbidden();
});

it('warns the owner when a limit is nearly spent', function () {
    $tenant = provision(['plan_key' => 'starter']);
    actingAsOwner($tenant);

    $limit = $tenant->limitOf('students');

    DB::connection(config('tenancy.database.central_connection'))->table('usage_records')->insert([
        'tenant_id' => $tenant->id, 'feature_key' => 'students', 'period' => null,
        'used' => (int) ($limit * 0.9), 'created_at' => now(), 'updated_at' => now(),
    ]);

    visitTenant($tenant)->assertOk()->assertSee('اقتربت من الحد');
});

it('does not offer a limit the plan does not grant at all', function () {
    $tenant = provision(['plan_key' => 'starter']);
    actingAsOwner($tenant);

    // الفروع ليست في باقة البداية — مكانها صفحة الترقية لا شريط استهلاك
    expect($tenant->limitOf('branches'))->toBe(0);

    visitTenant($tenant)->assertOk()->assertDontSee('عدد الفروع');
});

it('lets a team member land inside a tenant account with a valid ticket', function () {
    $tenant = provision();
    $owner = $tenant->run(fn () => User::where('role', 'owner')->firstOrFail());

    $token = tenancy()->impersonate($tenant, (string) $owner->getKey(), '/admin/dashboard', 'web');

    visitTenant($tenant, '/impersonate/'.$token->token)
        ->assertRedirectContains('/admin/dashboard');

    expect(DB::connection(config('tenancy.database.central_connection'))
        ->table('tenant_user_impersonation_tokens')->count())->toBe(0);
});

it('refuses a ticket that was already spent', function () {
    $tenant = provision();
    $owner = $tenant->run(fn () => User::where('role', 'owner')->firstOrFail());

    $token = tenancy()->impersonate($tenant, (string) $owner->getKey(), '/admin/dashboard', 'web');

    visitTenant($tenant, '/impersonate/'.$token->token);

    visitTenant($tenant, '/impersonate/'.$token->token)->assertNotFound();
});

it('flags the session so the tenant sees who is inside their account', function () {
    $tenant = provision();
    $owner = $tenant->run(fn () => User::where('role', 'owner')->firstOrFail());

    $token = tenancy()->impersonate($tenant, (string) $owner->getKey(), '/admin/dashboard', 'web');

    visitTenant($tenant, '/impersonate/'.$token->token);

    visitTenant($tenant)->assertOk()->assertSee('كعضو في فريق المنصة');
});
