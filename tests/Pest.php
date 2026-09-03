<?php

declare(strict_types=1);

use App\Core\Onboarding\OnboardingWizard;
use App\Core\Tenancy\Actions\ProvisionTenant;
use App\Core\Tenancy\Models\Tenant;
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
