<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ResourceController;
use App\Http\Middleware\ApplyTenantTheme;
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

    // نواة الإدارة: متحكّم واحد يخدم كل الموارد
    Route::get('/admin/{resource}', [ResourceController::class, 'index'])
        ->name('admin.resource.index');
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
