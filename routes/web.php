<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SuperAdminController;
use App\Http\Controllers\Admin\SuperAuthController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

/*
 | المسارات المركزية — موقعنا نحن (التسويق، التسجيل، لوحة الإدارة العليا).
 |
 | مقيّدة بالنطاقات المركزية حتى لا تتصادم مع مسارات المشتركين،
 | إذ يتشارك الاثنان نفس المسار "/" على نطاقات مختلفة.
 |
 | ADR-003: العربية بلا بادئة، والإنجليزية تحت /en/.
 */

$central = function (): void {
    Route::view('/', 'welcome')->name('home');

    // دخول فريق المنصة
    Route::get('/super/login', [SuperAuthController::class, 'show'])->name('super.login');
    Route::post('/super/login', [SuperAuthController::class, 'login']);
    Route::post('/super/logout', [SuperAuthController::class, 'logout'])->name('super.logout');

    // اللوحة العليا — أعمالنا نحن، لفريقنا وحده
    Route::middleware(EnsureSuperAdmin::class)->prefix('admin')->group(function (): void {
        Route::get('/', [SuperAdminController::class, 'overview'])->name('super.overview');
        Route::get('/usage', [SuperAdminController::class, 'usage'])->name('super.usage');
        Route::get('/health', [SuperAdminController::class, 'health'])->name('super.health');
        Route::get('/audit', [SuperAdminController::class, 'audit'])->name('super.audit');

        // الباقات والمزايا
        Route::get('/plans', [PlanController::class, 'index'])->name('super.plans');
        Route::put('/plans/{plan}/feature', [PlanController::class, 'updateFeature'])->name('super.plans.feature');

        /*
         | ملف المشترك — يسبق مسار الموارد العام، وإلا التقطه
         | /admin/{resource} على أنه مورد اسمه "tenants" بمعرّف.
         */
        Route::get('/tenants/{id}', [TenantController::class, 'show'])->name('super.tenant');
        Route::put('/tenants/{id}/status', [TenantController::class, 'updateStatus'])->name('super.tenant.status');
        Route::put('/tenants/{id}/plan', [TenantController::class, 'updatePlan'])->name('super.tenant.plan');
        Route::put('/tenants/{id}/feature', [TenantController::class, 'updateFeature'])->name('super.tenant.feature');
        Route::post('/tenants/{id}/impersonate', [ImpersonationController::class, 'start'])->name('super.tenant.impersonate');

        // نواة الموارد العامة — آخر ما يُسجَّل حتى لا يبتلع ما قبله
        Route::prefix('{resource}')->name('super.resource.')->group(function (): void {
            Route::get('/', [ResourceController::class, 'index'])->name('index');
            Route::get('/{id}/edit', [ResourceController::class, 'edit'])->name('edit');
            Route::put('/{id}', [ResourceController::class, 'update'])->name('update');
        });
    });

    // مرجع نظام التصميم — أداة للفريق، تُقيَّد بغير الإنتاج
    if (! app()->isProduction()) {
        Route::view('/design-system', 'design-system')->name('design-system');
    }
};

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () use ($central): void {
        $central();

        foreach (config('locales.prefixed') as $prefix) {
            Route::prefix($prefix)->name($prefix.'.')->group($central);
        }
    });
}
