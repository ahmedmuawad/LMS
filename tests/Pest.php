<?php

declare(strict_types=1);

use App\Core\Onboarding\OnboardingWizard;
use App\Core\Tenancy\Actions\ProvisionTenant;
use App\Core\Tenancy\Models\Tenant;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit');
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/*
 | اختبارات تعدّد المستأجرين لا تستخدم معاملة RefreshDatabase:
 | تبديل سياق المشترك يعيد اتصال القاعدة، فتضيع المعاملة وتفسد الحالة.
 | نُهاجر من الصفر لكل اختبار، وننظّف قواعد المشتركين من القرص.
 */
pest()->extend(TestCase::class)
    ->beforeEach(function (): void {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        Artisan::call('migrate:fresh', ['--force' => true]);
    })
    ->afterEach(function (): void {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach (glob(database_path('test_tenant*.sqlite')) ?: [] as $file) {
            @unlink($file);
        }
    })
    ->in('Tenancy');

/**
 * مصنع خفيف لاختبارات منطق النموذج فقط: يتخطّى أحداث التجهيز،
 * فلا يُنشئ قاعدة بيانات للمشترك. للتجهيز الحقيقي استخدم provision().
 */
function makeTenant(string $plan = 'growth', array $attributes = []): Tenant
{
    return Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'tenant-'.uniqid(),
        'name' => 'أكاديمية الاختبار',
        'slug' => 'test-'.uniqid(),
        'owner_email' => 'owner@example.test',
        'plan_key' => $plan,
        'status' => 'active',
        ...$attributes,
    ]));
}

/**
 * تجهيز حقيقي كامل: ينشئ قاعدة المشترك ويطبّق نمطه.
 *
 * يُنهي معالج التهيئة افتراضياً، لأن كل شاشات اللوحة تشترط إكماله.
 * مرّر onboarded: false لاختبار المعالج نفسه.
 */
function provision(array $overrides = [], bool $onboarded = true): Tenant
{
    $tenant = app(ProvisionTenant::class)->handle([
        'name' => 'أكاديمية الاختبار',
        'owner_email' => 'owner@example.test',
        'owner_name' => 'مالك الأكاديمية',
        'plan_key' => 'growth',
        ...$overrides,
    ]);

    if ($onboarded) {
        $tenant->run(fn () => app(OnboardingWizard::class)->complete());
    }

    return $tenant;
}

/**
 * يدخل كمالك المنصة — كل شاشات اللوحة تشترط حساباً بصلاحية إدارية.
 */
function actingAsOwner(Tenant $tenant): void
{
    $owner = $tenant->run(fn () => User::where('role', 'owner')->firstOrFail());

    if (! tenancy()->initialized) {
        tenancy()->initialize($tenant);
    }

    test()->actingAs($owner);
}

/**
 * يدخل كعضو في فريق المنصة — كل شاشات اللوحة العليا تشترطه.
 */
function actingAsSuperAdmin(array $overrides = []): SuperAdmin
{
    $admin = SuperAdmin::create([
        'name' => 'عضو الفريق',
        'email' => 'team-'.uniqid().'@platform.test',
        'password' => 'platform-password',
        'role' => 'super_admin',
        'is_active' => true,
        'email_verified_at' => now(),
        ...$overrides,
    ]);

    test()->actingAs($admin, 'super');

    return $admin;
}

/**
 * زيارة موقع المشترك بنطاقه — لا يكفي المسار وحده،
 * إذ تُحلّ هوية المشترك من النطاق لا من الجلسة.
 */
function tenantUrl(Tenant $tenant, string $path): string
{
    $domain = $tenant->domains->firstWhere('is_primary', true)?->domain
        ?? $tenant->domains()->first()->domain;

    return 'http://'.$domain.$path;
}

function tenantGet(Tenant $tenant, string $path)
{
    return test()->get(tenantUrl($tenant, $path));
}

function tenantPut(Tenant $tenant, string $path, array $data = [])
{
    return test()->put(tenantUrl($tenant, $path), $data);
}

function tenantPost(Tenant $tenant, string $path, array $data = [])
{
    return test()->post(tenantUrl($tenant, $path), $data);
}
