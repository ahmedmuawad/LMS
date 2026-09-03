<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ResourceController;
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

    // ---------- غرفة التعلّم ----------
    Route::middleware('auth')->group(function (): void {
        Route::get('/my-courses', MyCoursesController::class)->name('my-courses');
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
