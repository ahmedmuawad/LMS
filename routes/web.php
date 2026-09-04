<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SuperAdminController;
use App\Http\Controllers\Admin\SuperAuthController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Marketing\LandingController;
use App\Http\Controllers\Marketing\OwnerLoginController;
use App\Http\Controllers\Marketing\PlatformCheckoutController;
use App\Http\Controllers\Marketing\SignupController;
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
    Route::get('/', LandingController::class)->name('home');

    /*
     | التسجيل والدفع — من صفحة الأسعار إلى منصّة عاملة.
     |
     | محدودة المعدّل: التسجيل يُنشئ قاعدة بيانات، وفحص النطاق يكشف
     | أي الأسماء محجوز. كلاهما باب لمن يجرّب ألف اسم في الدقيقة.
     */
    Route::get('/start', [SignupController::class, 'show'])->name('start');
    Route::get('/start/slug', [SignupController::class, 'checkSlug'])
        ->middleware('throttle:60,1')->name('start.slug');
    Route::post('/start', [SignupController::class, 'store'])
        ->middleware('throttle:5,10')->name('start.store');

    Route::get('/start/{slug}/checkout', [SignupController::class, 'checkout'])->name('start.checkout');
    Route::post('/start/{slug}/trial', [SignupController::class, 'startTrial'])->name('start.trial');
    Route::post('/start/{slug}/pay', [PlatformCheckoutController::class, 'pay'])->name('start.pay');
    Route::get('/start/{slug}/return/{gateway}', [PlatformCheckoutController::class, 'return'])->name('start.return');
    Route::post('/start/{slug}/enter', [PlatformCheckoutController::class, 'enter'])->name('start.enter');

    // «ادخل إلى منصّتك» — دلالةٌ على نطاقه لا مصادقة عندنا
    Route::get('/login', [OwnerLoginController::class, 'show'])->name('owner.login');
    Route::post('/login', [OwnerLoginController::class, 'find'])
        ->middleware('throttle:10,1')->name('owner.login.find');

    /*
     | robots للنطاق المركزي.
     |
     | كان ملفاً ساكناً في public، وهو يسبق كل مسار — فكان يُقدَّم
     | لكل مشترك بدل ملفه هو، ويُبطل إعداد الفهرسة في شاشته.
     */
    Route::get('/robots.txt', fn () => response(
        implode("\n", [
            'User-agent: *',
            'Disallow: /admin/',
            'Disallow: /super/',
        ])."\n",
        200,
        ['Content-Type' => 'text/plain; charset=utf-8'],
    ))->name('central.robots');

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

        // الفواتير — التفصيل قبل المورد العام
        Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->name('super.invoice');
        Route::post('/invoices/{id}/pay', [InvoiceController::class, 'pay'])->name('super.invoice.pay');
        Route::put('/invoices/{id}/void', [InvoiceController::class, 'void'])->name('super.invoice.void');

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

/*
 | نفس المسارات على كل نطاق مركزي — وأسماؤها تختلف باختلافه.
 |
 | بغير بادئة الاسم يُسجَّل اسم `home` مرتين على نطاقين، فيرفض
 | `route:cache` التسلسل ويسقط الموقع كلّه بـ500 في الإنتاج. النطاق
 | الأول هو الأساسي فيحتفظ بالأسماء المجرّدة التي تشير إليها الشاشات،
 | وما بعده — عناوين التطوير — يأخذ بادئته.
 */
foreach (config('tenancy.central_domains') as $index => $domain) {
    $prefixName = $index === 0 ? '' : 'central'.$index.'.';

    Route::domain($domain)->name($prefixName)->group(function () use ($central): void {
        $central();

        foreach (config('locales.prefixed') as $prefix) {
            Route::prefix($prefix)->name($prefix.'.')->group($central);
        }
    });
}
