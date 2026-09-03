<?php

declare(strict_types=1);

use App\Core\Audit\Models\AuditLog;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
    actingAsSuperAdmin();
});

it('lists tenants and links each row to its file', function () {
    $tenant = provision();

    $this->get('/admin/tenants')
        ->assertOk()
        ->assertSee($tenant->name)
        ->assertSee('/admin/tenants/'.$tenant->id, false);
});

it('opens a tenant file with its plan, people and health', function () {
    $tenant = provision();

    $this->get('/admin/tenants/'.$tenant->id)
        ->assertOk()
        ->assertSee($tenant->name)
        ->assertSee($tenant->owner_email)
        ->assertSee('owner@example.test');
});

it('shows what the plan grants next to what is an exception', function () {
    $tenant = provision();

    DB::table('tenant_features')->insert([
        'tenant_id' => $tenant->id, 'feature_key' => 'scorm', 'value' => '1',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get('/admin/tenants/'.$tenant->id)->assertOk()->assertSee('استيراد SCORM');

    expect(Tenant::find($tenant->id)->allows('scorm'))->toBeTrue();
});

it('suspends a tenant from its file', function () {
    $tenant = provision();

    $this->put('/admin/tenants/'.$tenant->id.'/status', [
        'status' => 'suspended',
        'reason' => 'طلب العميل',
    ])->assertRedirect();

    expect(Tenant::find($tenant->id)->status)->toBe('suspended');
});

it('rejects an impossible status change with a field error, not a crash', function () {
    $tenant = provision();
    $before = $tenant->status;

    $this->from('/admin/tenants/'.$tenant->id)
        ->put('/admin/tenants/'.$tenant->id.'/status', ['status' => 'archived'])
        ->assertSessionHasErrors('status');

    expect(Tenant::find($tenant->id)->status)->toBe($before);
});

it('changes a plan from the tenant file', function () {
    $tenant = provision(['plan_key' => 'starter']);

    $this->put('/admin/tenants/'.$tenant->id.'/plan', ['plan_key' => 'professional'])
        ->assertRedirect();

    expect(Tenant::find($tenant->id)->plan_key)->toBe('professional');
});

it('grants a single feature as a documented exception', function () {
    $tenant = provision(['plan_key' => 'starter']);

    expect($tenant->allows('custom_domain'))->toBeFalse();

    $this->put('/admin/tenants/'.$tenant->id.'/feature', [
        'feature_key' => 'custom_domain',
        'value' => '1',
        'reason' => 'وعد المبيعات',
    ])->assertRedirect();

    expect(Tenant::find($tenant->id)->allows('custom_domain'))->toBeTrue();
});

it('takes an exception back by clearing the value', function () {
    $tenant = provision(['plan_key' => 'starter']);

    $this->put('/admin/tenants/'.$tenant->id.'/feature', ['feature_key' => 'custom_domain', 'value' => '1']);
    $this->put('/admin/tenants/'.$tenant->id.'/feature', ['feature_key' => 'custom_domain', 'value' => '']);

    expect(Tenant::find($tenant->id)->allows('custom_domain'))->toBeFalse();
});

it('edits the plan matrix and frees every subscriber from the old limits', function () {
    $tenant = provision(['plan_key' => 'starter']);

    $this->put('/admin/plans/starter/feature', ['feature_key' => 'students', 'value' => '7'])
        ->assertRedirect();

    expect(Tenant::find($tenant->id)->limitOf('students'))->toBe(7);
});

it('renders the plans matrix, usage, health and audit screens', function () {
    provision();

    $this->get('/admin/plans')->assertOk()->assertSee('مصفوفة المزايا');
    $this->get('/admin/usage')->assertOk()->assertSee('الاستهلاك والحدود');
    $this->get('/admin/health')->assertOk()->assertSee('صحة النظام');
    $this->get('/admin/audit')->assertOk()->assertSee('سجلّ التدخّلات');
});

it('reports a failing health check instead of hiding it', function () {
    config()->set('cache.default', 'database');   // لا يدعم الوسوم — شرط عزل المشتركين

    $this->get('/admin/health')->assertOk()->assertSee('فاشل');
});

it('hands out a one-time ticket to enter a tenant account', function () {
    $tenant = provision();

    $response = $this->post('/admin/tenants/'.$tenant->id.'/impersonate');

    $response->assertRedirectContains('/impersonate/');

    expect(DB::table('tenant_user_impersonation_tokens')->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('writes every entry into a tenant account to the audit log', function () {
    $tenant = provision();

    $this->post('/admin/tenants/'.$tenant->id.'/impersonate');

    $entry = AuditLog::where('action', 'tenant.impersonated')->firstOrFail();

    expect($entry->tenant_id)->toBe($tenant->id)
        ->and($entry->actor_name)->toBe('عضو الفريق')
        ->and($entry->meta['user_email'])->toBe('owner@example.test');
});

it('refuses to enter an account whose panel is closed', function () {
    $tenant = provision();
    Tenant::withoutEvents(fn () => $tenant->forceFill(['status' => 'suspended'])->save());

    $this->post('/admin/tenants/'.$tenant->id.'/impersonate')->assertStatus(409);

    expect(DB::table('tenant_user_impersonation_tokens')->count())->toBe(0);
});

it('refuses to enter as an account with no panel access', function () {
    $tenant = provision();

    $student = $tenant->run(fn () => User::create([
        'name' => 'طالب', 'email' => 'student@example.test',
        'password' => 'secret-password', 'role' => 'student', 'status' => 'active',
    ]));

    $this->post('/admin/tenants/'.$tenant->id.'/impersonate', ['user' => $student->getKey()])
        ->assertForbidden();
});

it('keeps the whole panel closed to a suspended team member', function () {
    auth('super')->user()->forceFill(['is_active' => false])->save();

    $this->get('/admin/plans')->assertForbidden();
    $this->get('/admin/usage')->assertForbidden();
    $this->get('/admin/health')->assertForbidden();
    $this->get('/admin/audit')->assertForbidden();
});
