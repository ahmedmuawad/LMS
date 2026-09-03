<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Center\AttendanceController;
use App\Http\Controllers\Center\FinanceController;
use App\Http\Controllers\Center\GuardianPortalController;
use App\Http\Controllers\Center\ScheduleController;
use App\Http\Controllers\Center\StudentFileController;
use App\Http\Controllers\Commerce\AdminOrderController;
use App\Http\Controllers\Commerce\CartController;
use App\Http\Controllers\Commerce\CheckoutController;
use App\Http\Controllers\Commerce\WalletController;
use App\Http\Controllers\Commerce\WebhookController;
use App\Http\Controllers\Community\DiscussionController;
use App\Http\Controllers\Community\ProgressController;
use App\Http\Controllers\Community\ReviewController;
use App\Http\Controllers\Content\ContentController;
use App\Http\Controllers\Content\MediaController;
use App\Http\Controllers\Content\PageBuilderController;
use App\Http\Controllers\Content\RedirectController;
use App\Http\Controllers\Growth\AffiliateController;
use App\Http\Controllers\Growth\CampaignController;
use App\Http\Controllers\Lms\AssignmentController;
use App\Http\Controllers\Lms\CatalogController;
use App\Http\Controllers\Lms\CertificateController;
use App\Http\Controllers\Lms\CurriculumController;
use App\Http\Controllers\Lms\GradingController;
use App\Http\Controllers\Lms\LearnController;
use App\Http\Controllers\Lms\MyCoursesController;
use App\Http\Controllers\Lms\QuizController;
use App\Http\Controllers\Notifications\InboxController;
use App\Http\Controllers\Notifications\NotificationAdminController;
use App\Http\Controllers\Pwa\PwaController;
use App\Http\Controllers\Services\ServiceController;
use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\ImpersonationController;
use App\Http\Controllers\Tenant\OnboardingController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Middleware\ApplyTenantTheme;
use App\Http\Middleware\EnsurePanelAccess;
use App\Http\Middleware\RequireOnboarding;
use App\Http\Middleware\TrackAffiliateReferral;
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

    // ---------- تطبيق الويب التقدّمي ----------
    Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('/icon.svg', [PwaController::class, 'icon'])->name('pwa.icon');
    Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');

    // استلام تذكرة «الدخول كمشترك» القادمة من لوحتنا العليا
    Route::get('/impersonate/{token}', ImpersonationController::class)
        ->name('impersonate');

    // ---------- الكتالوج العام ----------
    Route::get('/courses', [CatalogController::class, 'index'])->name('courses.index');
    Route::get('/courses/{slug}', [CatalogController::class, 'show'])->name('courses.show');
    Route::get('/certificate/{code}', [CertificateController::class, 'verify'])->name('certificate.verify');

    // ---------- المدونة والنماذج ----------
    Route::get('/blog', [ContentController::class, 'blog'])->name('blog');
    Route::get('/blog/{slug}', [ContentController::class, 'post'])->name('blog.post');
    Route::post('/blog/{slug}/comments', [ContentController::class, 'comment'])->name('blog.comment');
    Route::post('/forms/{key}', [ContentController::class, 'submitForm'])->name('forms.submit');

    // ---------- المجتمع ----------
    Route::get('/discussions', [DiscussionController::class, 'index'])->name('discussions');
    Route::get('/discussions/{id}', [DiscussionController::class, 'show'])->name('discussions.show');

    // ---------- الخدمات والحجوزات ----------
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{slug}', [ServiceController::class, 'show'])->name('services.show');
    Route::post('/services/{slug}/book', [ServiceController::class, 'book'])->name('services.book');
    Route::get('/my-bookings', [ServiceController::class, 'mine'])->middleware('auth')->name('bookings.mine');
    Route::get('/bookings/{token}', [ServiceController::class, 'booking'])->name('bookings.show');
    Route::post('/bookings/{token}/cancel', [ServiceController::class, 'cancel'])->name('bookings.cancel');

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

        // ---------- المجتمع والتحفيز ----------
        Route::post('/courses/{slug}/discussions', [DiscussionController::class, 'store'])->name('discussions.store');
        Route::post('/discussions/{id}/replies', [DiscussionController::class, 'reply'])->name('discussions.reply');
        Route::post('/discussions/{id}/vote', [DiscussionController::class, 'vote'])->name('discussions.vote');
        Route::post('/discussions/{id}/replies/{replyId}/vote', [DiscussionController::class, 'vote'])->name('discussions.reply.vote');
        Route::post('/discussions/{id}/replies/{replyId}/accept', [DiscussionController::class, 'accept'])->name('discussions.accept');

        Route::post('/courses/{slug}/reviews', [ReviewController::class, 'storeCourse'])->name('reviews.course');
        Route::post('/services/{slug}/reviews', [ReviewController::class, 'storeService'])->name('reviews.service');

        Route::get('/my-progress', [ProgressController::class, 'show'])->name('progress');
        Route::get('/affiliate', [AffiliateController::class, 'dashboard'])->name('affiliate');
        Route::post('/affiliate/join', [AffiliateController::class, 'join'])->name('affiliate.join');
        Route::get('/leaderboard', [ProgressController::class, 'leaderboard'])->name('leaderboard');

        // ---------- الإشعارات ----------
        Route::get('/notifications', [InboxController::class, 'index'])->name('notifications');
        Route::post('/notifications/read-all', [InboxController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{id}/read', [InboxController::class, 'read'])->name('notifications.read');
        Route::get('/account/notifications', [InboxController::class, 'preferences'])->name('account.notifications');
        Route::put('/account/notifications', [InboxController::class, 'savePreferences'])->name('account.notifications.save');
        Route::post('/account/push', [InboxController::class, 'subscribe'])->name('account.push.subscribe');
        Route::delete('/account/push', [InboxController::class, 'unsubscribe'])->name('account.push.unsubscribe');

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

    // ---------- السنتر ----------
    Route::prefix('admin')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.center.')->group(function (): void {
            Route::get('/attendance', [AttendanceController::class, 'today'])->name('attendance');
            Route::get('/attendance/{session}', [AttendanceController::class, 'show'])->name('attendance.show');
            Route::post('/attendance/{session}', [AttendanceController::class, 'store'])->name('attendance.store');
            Route::post('/attendance/{session}/mark', [AttendanceController::class, 'mark'])->name('attendance.mark');

            Route::get('/schedule', [ScheduleController::class, 'week'])->name('schedule');
            Route::post('/schedule/check', [ScheduleController::class, 'check'])->name('schedule.check');
            Route::post('/schedule/sessions', [ScheduleController::class, 'storeSession'])->name('schedule.session');
            Route::post('/groups/{group}/generate', [ScheduleController::class, 'generate'])->name('schedule.generate');
            Route::put('/sessions/{session}/cancel', [ScheduleController::class, 'cancelSession'])->name('session.cancel');

            Route::get('/fees', [FinanceController::class, 'arrears'])->name('arrears');
            Route::post('/fees/collect', [FinanceController::class, 'collect'])->name('fees.collect');
            Route::post('/groups/{group}/invoices', [FinanceController::class, 'issue'])->name('fees.issue');
            Route::get('/cashboxes', [FinanceController::class, 'cashboxes'])->name('cashboxes');
            Route::post('/cashboxes/{cashbox}/close', [FinanceController::class, 'close'])->name('cashboxes.close');

            Route::get('/center-students/{id}', [StudentFileController::class, 'show'])->name('student');
            Route::get('/center-students/{id}/report', [StudentFileController::class, 'monthly'])->name('student.report');
        });

    // بوابة ولي الأمر
    Route::middleware('auth')->prefix('guardian')->name('guardian.')->group(function (): void {
        Route::get('/', [GuardianPortalController::class, 'index'])->name('index');
        Route::get('/children/{student}', [GuardianPortalController::class, 'child'])->name('child');
    });

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

    // المحتوى: باني الصفحات والوسائط — قبل مسار الموارد العام
    Route::prefix('admin')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.')->group(function (): void {
            Route::get('/page-builder', [PageBuilderController::class, 'index'])->name('page-builder.index');
            Route::post('/page-builder', [PageBuilderController::class, 'store'])->name('page-builder.store');
            Route::get('/page-builder/{id}', [PageBuilderController::class, 'edit'])->name('page-builder.edit');
            Route::put('/page-builder/{id}', [PageBuilderController::class, 'update'])->name('page-builder.update');

            Route::get('/media', [MediaController::class, 'index'])->name('media.index');
            Route::post('/media', [MediaController::class, 'store'])->name('media.store');
            Route::put('/media/{id}', [MediaController::class, 'update'])->name('media.update');
            Route::delete('/media/{id}', [MediaController::class, 'destroy'])->name('media.destroy');
        });

    // النمو: المسوّقون والتسلسلات — قبل مسار الموارد العام
    Route::prefix('admin')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.')->group(function (): void {
            Route::get('/affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
            Route::put('/affiliates/{id}', [AffiliateController::class, 'update'])->name('affiliates.update');
            Route::put('/affiliate-conversions/{id}/reject', [AffiliateController::class, 'rejectConversion'])->name('affiliates.reject');

            Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
            Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
            Route::get('/campaigns/{id}', [CampaignController::class, 'edit'])->name('campaigns.edit');
            Route::put('/campaigns/{id}', [CampaignController::class, 'update'])->name('campaigns.update');
        });

    // مراجعة التقييمات — قبل مسار الموارد العام
    Route::prefix('admin/reviews')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.reviews.')->group(function (): void {
            Route::get('/', [ReviewController::class, 'queue'])->name('queue');
            Route::put('/{type}/{id}', [ReviewController::class, 'moderate'])->name('moderate');
        });

    // الإشعارات: المصفوفة والقوالب والسجلّ — قبل مسار الموارد العام
    Route::prefix('admin/notifications')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.notifications.')->group(function (): void {
            Route::get('/', [NotificationAdminController::class, 'matrix'])->name('matrix');
            Route::put('/', [NotificationAdminController::class, 'saveMatrix'])->name('matrix.save');
            Route::get('/logs', [NotificationAdminController::class, 'logs'])->name('logs');
            Route::get('/{event}', [NotificationAdminController::class, 'edit'])->name('edit');
            Route::put('/{event}', [NotificationAdminController::class, 'update'])->name('update');
            Route::post('/{event}/test', [NotificationAdminController::class, 'test'])->name('test');
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

    /*
     | الصفحة الحرّة آخر المسارات: ما لم يلتقطه مسار معلوم قد يكون
     | صفحة أنشأها المشترك. بادئات اللغة مستثناة كي لا تُقرأ سلugاً.
     */
    Route::get('/{slug}', [ContentController::class, 'page'])
        ->where('slug', '(?!(?:'.implode('|', config('locales.prefixed')).')$)[A-Za-z0-9_-]+')
        ->name('page');
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
    TrackAffiliateReferral::class,
])->group(function () use ($tenantRoutes): void {
    $tenantRoutes();

    /*
     | عامل الخدمة خارج بادئات اللغة: نطاقه هو مساره، وتكراره تحت
     | /en يعني عاملَين لا يعرف أحدهما ما خزّنه الآخر.
     */
    Route::get('/service-worker.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');

    foreach (config('locales.prefixed') as $prefix) {
        Route::prefix($prefix)->name($prefix.'.')->group($tenantRoutes);
    }

    // آخر محطة قبل الـ404: جدول تحويلات الموقع القديم
    Route::fallback(RedirectController::class);
});
