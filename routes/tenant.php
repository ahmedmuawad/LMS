<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Commerce\AdminOrderController;
use App\Http\Controllers\Commerce\CartController;
use App\Http\Controllers\Commerce\CheckoutController;
use App\Http\Controllers\Commerce\WalletController;
use App\Http\Controllers\Commerce\WebhookController;
use App\Http\Controllers\Lms\AssignmentController;
use App\Http\Controllers\Lms\CatalogController;
use App\Http\Controllers\Lms\CertificateController;
use App\Http\Controllers\Lms\CurriculumController;
use App\Http\Controllers\Lms\GradingController;
use App\Http\Controllers\Lms\LearnController;
use App\Http\Controllers\Lms\MyCoursesController;
use App\Http\Controllers\Lms\QuizController;
use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\ImpersonationController;
use App\Http\Controllers\Tenant\OnboardingController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Middleware\ApplyTenantTheme;
use App\Http\Middleware\EnsurePanelAccess;
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

    // استلام تذكرة «الدخول كمشترك» القادمة من لوحتنا العليا
    Route::get('/impersonate/{token}', ImpersonationController::class)
        ->name('impersonate');

    // ---------- الكتالوج العام ----------
    Route::get('/courses', [CatalogController::class, 'index'])->name('courses.index');
    Route::get('/courses/{slug}', [CatalogController::class, 'show'])->name('courses.show');
    Route::get('/certificate/{code}', [CertificateController::class, 'verify'])->name('certificate.verify');

    // ---------- السلة والدفع ----------
    Route::get('/cart', [CartController::class, 'show'])->name('cart');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::put('/cart/items/{item}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/items/{item}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon');
    Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');
    Route::get('/checkout/return/{gateway}', [WebhookController::class, 'return'])->name('checkout.return');
    Route::get('/orders/{number}', [CheckoutController::class, 'order'])->name('orders.show');

    // ---------- غرفة التعلّم ----------
    Route::middleware('auth')->group(function (): void {
        Route::get('/my-courses', MyCoursesController::class)->name('my-courses');
        Route::get('/wallet', [WalletController::class, 'show'])->name('wallet');
        Route::post('/wallet/redeem', [WalletController::class, 'redeem'])->name('wallet.redeem');
        Route::post('/courses/{slug}/enroll', [LearnController::class, 'enroll'])->name('courses.enroll');

        Route::get('/learn/{slug}', [LearnController::class, 'room'])->name('learn');
        Route::get('/learn/{slug}/quiz/{item}/attempt/{attempt}', [QuizController::class, 'attempt'])->name('learn.quiz.attempt');
        Route::post('/learn/{slug}/quiz/{item}/attempt/{attempt}', [QuizController::class, 'submit']);
        Route::post('/learn/{slug}/quiz/{item}/start', [QuizController::class, 'start'])->name('learn.quiz.start');
        Route::post('/learn/{slug}/{item}/assignment', [AssignmentController::class, 'submit'])->name('learn.assignment');
        Route::get('/learn/{slug}/{item}', [LearnController::class, 'room'])->name('learn.item');
        Route::post('/learn/{slug}/{item}/heartbeat', [LearnController::class, 'heartbeat'])->name('learn.heartbeat');
        Route::post('/learn/{slug}/{item}/complete', [LearnController::class, 'complete'])->name('learn.complete');
    });

    // المصادقة
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AuthController::class, 'show'])->name('login');
        Route::post('/login', [AuthController::class, 'login']);
    });
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

    // معالج التهيئة — يسبق اللوحة
    Route::prefix('onboarding')->middleware(EnsurePanelAccess::class)->name('onboarding.')->group(function (): void {
        Route::get('/', fn () => redirect(url('/onboarding/mode')));
        Route::post('/finish', [OnboardingController::class, 'finish'])->name('finish');
        Route::get('/{step}', [OnboardingController::class, 'show'])->name('show');
        Route::post('/{step}', [OnboardingController::class, 'store'])->name('store');
    });

    // اللوحة — قبل مسار الموارد، وإلا التقطها كمورد اسمه dashboard
    Route::get('/admin/dashboard', DashboardController::class)
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.dashboard');

    // إدارة الطلبات والأكواد — قبل مسار الموارد العام
    Route::prefix('admin')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.commerce.')->group(function (): void {
            Route::get('/orders/{id}', [AdminOrderController::class, 'show'])->name('order');
            Route::post('/orders/{id}/pay', [AdminOrderController::class, 'pay'])->name('order.pay');
            Route::put('/orders/{id}/cancel', [AdminOrderController::class, 'cancel'])->name('order.cancel');
            Route::post('/orders/{id}/refund', [AdminOrderController::class, 'refund'])->name('order.refund');
            Route::put('/refunds/{refund}', [AdminOrderController::class, 'handleRefund'])->name('refund.handle');

            Route::get('/recharge-codes/generate', [AdminOrderController::class, 'codes'])->name('codes');
            Route::post('/recharge-codes/generate', [AdminOrderController::class, 'generateCodes'])->name('codes.generate');
            Route::get('/recharge-codes/batches/{batch}/export', [AdminOrderController::class, 'exportBatch'])->name('codes.export');
        });

    // طاولة التصحيح
    Route::prefix('admin/grading')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.grading.')->group(function (): void {
            Route::get('/', [GradingController::class, 'index'])->name('index');
            Route::get('/attempts/{attempt}', [GradingController::class, 'attempt'])->name('attempt');
            Route::put('/attempts/{attempt}/answers/{answer}', [GradingController::class, 'gradeAnswer'])->name('attempt.answer');
            Route::get('/submissions/{submission}', [GradingController::class, 'submission'])->name('submission');
            Route::put('/submissions/{submission}', [GradingController::class, 'gradeSubmission'])->name('submission.grade');
        });

    // باني المنهج — قبل مسار الموارد العام
    Route::prefix('admin/courses/{course}')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.curriculum.')->group(function (): void {
            Route::get('/curriculum', [CurriculumController::class, 'show'])->name('show');
            Route::post('/sections', [CurriculumController::class, 'addSection'])->name('sections.store');
            Route::delete('/sections/{section}', [CurriculumController::class, 'removeSection'])->name('sections.destroy');
            Route::post('/items', [CurriculumController::class, 'addItem'])->name('items.store');
            Route::put('/items/order', [CurriculumController::class, 'reorder'])->name('items.reorder');
            Route::put('/items/{item}', [CurriculumController::class, 'updateItem'])->name('items.update');
            Route::delete('/items/{item}', [CurriculumController::class, 'removeItem'])->name('items.destroy');
        });

    // الاشتراك والفواتير
    Route::get('/admin/billing', BillingController::class)
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.billing');

    // الإعدادات — قبل مسار الموارد
    Route::prefix('admin/settings')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.settings.')->group(function (): void {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::get('/{group}', [SettingsController::class, 'show'])->name('show');
            Route::put('/{group}', [SettingsController::class, 'update'])->name('update');
        });

    // نواة الإدارة: متحكّم واحد يخدم كل الموارد
    Route::prefix('admin/{resource}')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.resource.')->group(function (): void {
            Route::get('/', [ResourceController::class, 'index'])->name('index');
            Route::get('/create', [ResourceController::class, 'create'])->name('create');
            Route::post('/', [ResourceController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [ResourceController::class, 'edit'])->name('edit');
            Route::put('/{id}', [ResourceController::class, 'update'])->name('update');
            Route::delete('/{id}', [ResourceController::class, 'destroy'])->name('destroy');
        });
};

/*
 | ردّ البوابات: بلا جلسة ولا CSRF — البوابة لا تحمل رمزاً، والتحقق
 | من التوقيع داخل كل بوابة هو ما يحرس هذا المسار.
 */
Route::middleware([InitializeTenancyByDomain::class])
    ->post('/webhooks/payments/{gateway}', WebhookController::class)
    ->name('webhooks.payments');

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
