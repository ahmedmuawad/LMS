<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Tenant\OnboardingController;
use App\Http\Middleware\ApplyTenantTheme;
use App\Http\Middleware\RequireOnboarding;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
 | مسارات المشترك — موقعه العام ولوحاته.
 |
 | تُحلّ هوية المشترك من النطاق (فرعي أو خاص)، ثم يبدّل التطبيق
 | الاتصال إلى قاعدة المشترك ويعزل الكاش والتخزين والطوابير.
 |
 | ADR-003: نفس قاعدة بادئة اللغة تنطبق داخل موقع كل مشترك.
 */

$tenantRoutes = function (): void {
    Route::get('/', fn () => view('tenant.home'))->name('tenant.home');

    // معالج التهيئة — يسبق اللوحة
    Route::prefix('onboarding')->name('onboarding.')->group(function (): void {
        Route::get('/', fn () => redirect(url('/onboarding/mode')));
        Route::post('/finish', [OnboardingController::class, 'finish'])->name('finish');
        Route::get('/{step}', [OnboardingController::class, 'show'])->name('show');
        Route::post('/{step}', [OnboardingController::class, 'store'])->name('store');
    });

    // نواة الإدارة: متحكّم واحد يخدم كل الموارد
    Route::prefix('admin/{resource}')->middleware(RequireOnboarding::class)->name('admin.resource.')->group(function (): void {
        Route::get('/', [ResourceController::class, 'index'])->name('index');
        Route::get('/create', [ResourceController::class, 'create'])->name('create');
        Route::post('/', [ResourceController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [ResourceController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ResourceController::class, 'update'])->name('update');
        Route::delete('/{id}', [ResourceController::class, 'destroy'])->name('destroy');
    });
};

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    ApplyTenantTheme::class,      // بعد تهيئة المشترك، لا قبلها
])->group(function () use ($tenantRoutes): void {
    $tenantRoutes();

    foreach (config('locales.prefixed') as $prefix) {
        Route::prefix($prefix)->name($prefix.'.')->group($tenantRoutes);
    }
});
