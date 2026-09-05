<?php

declare(strict_types=1);

use App\Core\Access\Ability;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Auth\DeviceController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Center\AttendanceController;
use App\Http\Controllers\Center\DeviceController as AttendanceDeviceController;
use App\Http\Controllers\Center\FinanceController;
use App\Http\Controllers\Center\GroupEnrolmentController;
use App\Http\Controllers\Center\GuardianPortalController;
use App\Http\Controllers\Center\MyClassesController;
use App\Http\Controllers\Center\ScheduleController;
use App\Http\Controllers\Center\StudentFileController;
use App\Http\Controllers\Commerce\AdminOrderController;
use App\Http\Controllers\Commerce\CartController;
use App\Http\Controllers\Commerce\CheckoutController;
use App\Http\Controllers\Commerce\ShopController;
use App\Http\Controllers\Commerce\WalletController;
use App\Http\Controllers\Commerce\WebhookController;
use App\Http\Controllers\Community\DiscussionController;
use App\Http\Controllers\Community\ProgressController;
use App\Http\Controllers\Community\ReviewController;
use App\Http\Controllers\Content\ContentController;
use App\Http\Controllers\Content\MediaController;
use App\Http\Controllers\Content\PageBuilderController;
use App\Http\Controllers\Content\SearchController;
use App\Http\Controllers\Content\RedirectController;
use App\Http\Controllers\Growth\AffiliateController;
use App\Http\Controllers\Growth\CampaignController;
use App\Http\Controllers\Instructor\AnnouncementController;
use App\Http\Controllers\Instructor\DiscussionController as InstructorDiscussionController;
use App\Http\Controllers\Instructor\EarningsController;
use App\Http\Controllers\Instructor\StatisticsController;
use App\Http\Controllers\Instructor\StudentController as InstructorStudentController;
use App\Http\Controllers\Lms\AttachmentController;
use App\Http\Controllers\Lms\AiQuestionController;
use App\Http\Controllers\Lms\AssignmentController;
use App\Http\Controllers\Lms\CatalogController;
use App\Http\Controllers\Lms\CertificateController;
use App\Http\Controllers\Lms\CurriculumController;
use App\Http\Controllers\Lms\GradingController;
use App\Http\Controllers\Lms\LearnController;
use App\Http\Controllers\Lms\LessonAttachmentController;
use App\Http\Controllers\Lms\LearningRuleController;
use App\Http\Controllers\Lms\MyCoursesController;
use App\Http\Controllers\Lms\QuizController;
use App\Http\Controllers\Lms\VideoMomentController;
use App\Http\Controllers\Lms\ScormController;
use App\Http\Controllers\Lms\StudentAreaController;
use App\Http\Controllers\Lms\StudentDashboardController;
use App\Http\Controllers\Notifications\InboxController;
use App\Http\Controllers\Notifications\NotificationAdminController;
use App\Http\Controllers\Pwa\PwaController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Seo\SitemapController;
use App\Http\Controllers\Services\ServiceController;
use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\ApiTokenController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\HomeController;
use App\Http\Controllers\Tenant\ImpersonationController;
use App\Http\Controllers\Tenant\OnboardingController;
use App\Http\Controllers\Tenant\PlatformModeController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Controllers\Tenant\UsageController;
use App\Http\Controllers\Api\ApiController;
use App\Http\Middleware\ApplyTenantTheme;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureAbility;
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
    /*
     | الصفحة الرئيسية — كانت لافتةً ثابتة «منصّتك جاهزة».
     |
     | يراها طلّابه وأولياء أمورهم فيظنّون الموقع قيد الإنشاء، وهي
     | أول ما يرونه وأكثر ما يُشارَك رابطه.
     */
    Route::get('/', HomeController::class)->name('tenant.home');

    // ---------- تطبيق الويب التقدّمي ----------
    Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('/icon.svg', [PwaController::class, 'icon'])->name('pwa.icon');
    Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');

    // استلام تذكرة «الدخول كمشترك» القادمة من لوحتنا العليا
    Route::get('/impersonate/{token}', ImpersonationController::class)
        ->name('impersonate');

    /*
     | الواجهة البرمجية العامة.
     |
     | خارج مِدلوير الويب: التكامل يعمل من خادمٍ آخر بلا كوكيّات
     | ولا CSRF، والمفتاح في ترويسة `Authorization` هو ما تفهمه كل
     | أداة. وكل نقطة تُحرَس بنطاقها، فمفتاحُ القراءة لا يكتب.
     */
    /*
     | نقطة استقبال بصمات أجهزة الحضور.
     |
     | خارج مِدلوير الويب: الجهاز لا يحمل كوكيّات ولا CSRF، ومفتاحه
     | في ترويسة `Authorization` — وهذا ما تستطيعه أبسط الأجهزة.
     */
    Route::post('api/v1/punch', [AttendanceDeviceController::class, 'punch'])->name('api.punch');

    Route::prefix('api/v1')->name('api.')->group(function (): void {
        Route::middleware(AuthenticateApiToken::class)->group(function (): void {
            Route::get('/me', [ApiController::class, 'me'])->name('me');
        });

        Route::get('/courses', [ApiController::class, 'courses'])
            ->middleware(AuthenticateApiToken::class.':courses:read')->name('courses');

        Route::get('/students', [ApiController::class, 'students'])
            ->middleware(AuthenticateApiToken::class.':students:read')->name('students');

        Route::get('/groups', [ApiController::class, 'groups'])
            ->middleware(AuthenticateApiToken::class.':groups:read')->name('groups');

        Route::get('/enrollments', [ApiController::class, 'enrollments'])
            ->middleware(AuthenticateApiToken::class.':enrollments:read')->name('enrollments');

        Route::post('/enrollments', [ApiController::class, 'enrol'])
            ->middleware(AuthenticateApiToken::class.':enrollments:write')->name('enrol');

        Route::get('/attendance', [ApiController::class, 'attendance'])
            ->middleware(AuthenticateApiToken::class.':attendance:read')->name('attendance');

        Route::get('/invoices', [ApiController::class, 'invoices'])
            ->middleware(AuthenticateApiToken::class.':invoices:read')->name('invoices');
    });

    // ---------- الكتالوج العام ----------
    Route::get('/courses', [CatalogController::class, 'index'])->name('courses.index');
    Route::get('/courses/{slug}', [CatalogController::class, 'show'])->name('courses.show');
    Route::get('/certificate/{code}', [CertificateController::class, 'verify'])->name('certificate.verify');

    /*
     | البحث الموحّد والمتجر — كانا غائبين عن الموقع.
     |
     | الزائر كان يجد كتالوج الكورسات وحده: يبحث عن مقال أو خدمة فلا
     | يجده فيظنّها غير موجودة، والمنتجات تُدار في اللوحة وتُباع في
     | السلة بلا صفحةٍ تعرضها.
     */
    Route::get('/search', SearchController::class)->name('search');

    Route::get('/shop', [ShopController::class, 'index'])->name('shop');
    Route::get('/shop/{slug}', [ShopController::class, 'show'])->name('shop.show');

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
        // مدخل الطالب — الشاشة التي تجمع ما كان اثنتي عشرة شاشة متفرّقة
        Route::get('/me', StudentDashboardController::class)->name('me');

        Route::get('/my-courses', MyCoursesController::class)->name('my-courses');

        // حصص الطالب ومجموعاته ورابط دخولها — كان الرابط يُحفظ ولا يصل صاحبه
        Route::get('/my-classes', MyClassesController::class)->name('my-classes');

        // إجابة نقطة تفاعل — تُقيَّم فوراً، فالفائدة في أن يعرف الآن
        Route::post('/moments/{moment}/respond', [VideoMomentController::class, 'respond'])
            ->whereNumber('moment')->name('moments.respond');

        // حالة SCORM — يُنادى من جسر الحزمة كل دقيقة وعند الإغلاق
        Route::post('/scorm/{package}/state', [ScormController::class, 'state'])
            ->whereNumber('package')->name('scorm.state');

        /*
         | مرفقات الدروس — محروسةً لا بروابط تخزين عامة.
         |
         | رابط التخزين العام يُنسخ إلى مجموعة واتساب فيقرؤه مئةٌ لم
         | يدفعوا. وهنا لا يمرّ بايتٌ قبل التحقّق من التسجيل.
         */
        Route::get('/attachments/{id}', [AttachmentController::class, 'show'])
            ->whereNumber('id')->name('attachments.show');
        Route::get('/attachments/{id}/file', [AttachmentController::class, 'stream'])
            ->whereNumber('id')->name('attachments.file');

        /*
         | بقية لوحة الطالب.
         |
         | كانت قائمته تعرض هذه الروابط الستة قبل بناء شاشاتها،
         | فيقع على ٤٠٤ من يضغطها. لا يُعرض في قائمة رابطٌ لا تفتحه
         | شاشة — وهذه القاعدة تُغلق هنا.
         */
        Route::get('/my-certificates', [StudentAreaController::class, 'certificates'])->name('my-certificates');
        Route::get('/my-badges', [StudentAreaController::class, 'badges'])->name('my-badges');
        Route::get('/my-orders', [StudentAreaController::class, 'orders'])->name('my-orders');
        Route::get('/my-services', [StudentAreaController::class, 'services'])->name('my-services');

        Route::get('/my-notes', [StudentAreaController::class, 'notes'])->name('notes');
        Route::post('/my-notes', [StudentAreaController::class, 'storeNote'])->name('notes.store');
        Route::put('/my-notes/{id}', [StudentAreaController::class, 'updateNote'])->name('notes.update');
        Route::delete('/my-notes/{id}', [StudentAreaController::class, 'destroyNote'])->name('notes.destroy');

        // اشتراكاتي — عضويةٌ شهرية تفتح المحتوى بدل شراء كل كورس
        Route::get('/my-memberships', [StudentAreaController::class, 'memberships'])->name('my-memberships');
        Route::post('/my-memberships/{id}/cancel', [StudentAreaController::class, 'cancelMembership'])
            ->whereNumber('id')->name('my-memberships.cancel');

        Route::get('/wishlist', [StudentAreaController::class, 'wishlist'])->name('wishlist');
        Route::post('/wishlist', [StudentAreaController::class, 'toggleWishlist'])->name('wishlist.toggle');

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
        // حدث مراقبة — يصل بـsendBeacon عند مغادرة الصفحة
        Route::post('/learn/{slug}/quiz/{item}/attempt/{attempt}/event', [QuizController::class, 'event'])
            ->name('quiz.event');

        Route::post('/learn/{slug}/quiz/{item}/attempt/{attempt}', [QuizController::class, 'submit'])->name('learn.quiz.submit');
        Route::post('/learn/{slug}/quiz/{item}/start', [QuizController::class, 'start'])->name('learn.quiz.start');
        Route::post('/learn/{slug}/{item}/assignment', [AssignmentController::class, 'submit'])->name('learn.assignment');
        Route::get('/learn/{slug}/{item}', [LearnController::class, 'room'])->name('learn.item');
        Route::post('/learn/{slug}/{item}/heartbeat', [LearnController::class, 'heartbeat'])->name('learn.heartbeat');
        Route::post('/learn/{slug}/{item}/complete', [LearnController::class, 'complete'])->name('learn.complete');
    });

    /*
     | المصادقة — وثيقة 11 §ب.
     |
     | كانت شاشة الدخول وحدها: لا تسجيل ولا تحقّق بريد ولا استعادة
     | كلمة مرور ولا توثيق بخطوتين.
     */
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AuthController::class, 'show'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

        // مخرجٌ لمن بلغ حدّ أجهزته: شاشة الفكّ خلف الدخول، والمقفول لا يصلها
        Route::post('/login/release-device', [AuthController::class, 'releaseDevice'])
            ->name('login.release-device');

        Route::get('/register', [RegisterController::class, 'show'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

        Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'send'])->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');

        // تحدّي التوثيق: الجلسة لم تُنشأ بعد، فالزائر هو من يصلها
        Route::get('/two-factor', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
        Route::post('/two-factor', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

    Route::middleware('auth')->group(function (): void {
        Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
        Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')->name('verification.verify');
        Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->name('verification.send');

        // حساب المستخدم — وثيقة 11 §ج
        Route::get('/account', [ProfileController::class, 'show'])->name('account');
        Route::put('/account', [ProfileController::class, 'update'])->name('account.update');
        Route::put('/account/password', [ProfileController::class, 'updatePassword'])->name('account.password');

        /*
         | تغيير بريد الدخول — الرابط يُفتح من الصندوق الجديد، فيُبدَّل.
         | محدود المعدّل: كل طلب يُرسل بريداً إلى عنوان يكتبه المستخدم.
         */
        Route::put('/account/email', [ProfileController::class, 'requestEmailChange'])
            ->middleware('throttle:5,10')->name('account.email');
        Route::delete('/account/email', [ProfileController::class, 'cancelEmailChange'])->name('account.email.cancel');
        Route::get('/account/email/{token}', [ProfileController::class, 'confirmEmailChange'])->name('account.email.confirm');
        Route::delete('/account', [ProfileController::class, 'destroy'])->name('account.destroy');

        Route::get('/account/two-factor', [TwoFactorController::class, 'setup'])->name('account.two-factor');
        Route::post('/account/two-factor', [TwoFactorController::class, 'enable'])->name('account.two-factor.enable');
        Route::delete('/account/two-factor', [TwoFactorController::class, 'disable'])->name('account.two-factor.disable');

        // فصل جهاز — الحدّ بلا شاشةٍ لفكّه سجنٌ لا قاعدة
        Route::delete('/account/devices/{id}', [DeviceController::class, 'destroy'])
            ->whereNumber('id')->name('account.devices.destroy');
    });

    // معالج التهيئة — يسبق اللوحة
    Route::prefix('onboarding')->middleware(EnsurePanelAccess::class)->name('onboarding.')->group(function (): void {
        Route::get('/', fn () => redirect(url('/onboarding/mode')));
        Route::post('/finish', [OnboardingController::class, 'finish'])->name('finish');
        Route::get('/{step}', [OnboardingController::class, 'show'])->name('show');
        Route::post('/{step}', [OnboardingController::class, 'store'])->name('store');
    });

    // اللوحة — قبل مسار الموارد، وإلا التقطها كمورد اسمه dashboard
    Route::get('/admin/dashboard', DashboardController::class)
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class, EnsureAbility::class.':'.Ability::DASHBOARD])
        ->name('admin.dashboard');

    // ---------- السنتر ----------
    Route::prefix('admin')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class, EnsureAbility::class.':'.Ability::CENTER_VIEW])
        ->name('admin.center.')->group(function (): void {
            Route::get('/attendance', [AttendanceController::class, 'today'])->name('attendance');
            Route::get('/attendance/{session}', [AttendanceController::class, 'show'])->name('attendance.show');
            Route::post('/attendance/{session}', [AttendanceController::class, 'store'])->name('attendance.store');
            Route::post('/attendance/{session}/mark', [AttendanceController::class, 'mark'])->name('attendance.mark');

            Route::get('/schedule', [ScheduleController::class, 'week'])->name('schedule');

            // إشغال القاعات ومواعيد المجموعة المتكرّرة
            Route::get('/rooms-occupancy', [ScheduleController::class, 'rooms'])->name('rooms');
            Route::get('/center-teachers', [ScheduleController::class, 'teachers'])->name('teachers');
            Route::get('/groups/{group}/slots', [ScheduleController::class, 'slots'])->name('slots');
            Route::post('/groups/{group}/slots', [ScheduleController::class, 'storeSlot'])->name('slots.store');
            Route::post('/groups/{group}/slots/check', [ScheduleController::class, 'checkSlot'])->name('slots.check');
            Route::delete('/groups/{group}/slots/{slot}', [ScheduleController::class, 'destroySlot'])->name('slots.destroy');
            Route::post('/schedule/check', [ScheduleController::class, 'check'])->name('schedule.check');
            Route::post('/schedule/sessions', [ScheduleController::class, 'storeSession'])->name('schedule.session');
            Route::post('/groups/{group}/generate', [ScheduleController::class, 'generate'])->name('schedule.generate');
            Route::put('/sessions/{session}/cancel', [ScheduleController::class, 'cancelSession'])->name('session.cancel');

            Route::get('/fees', [FinanceController::class, 'arrears'])->name('arrears');

            /*
             | إصدار الأقساط — كان يُبنى ولا يُوصَل.
             |
             | `FinanceController::issue()` موجودة منذ البداية بلا
             | مسارٍ ولا زر، فصاحب المركز يرى «لا مستحقات» وعليه
             | طلبةٌ لم تُقيَّد أقساطهم قط. وقدرةٌ لا يصلها المستخدم
             | ليست قدرة.
             */
            Route::post('/fees/issue', [FinanceController::class, 'issueAll'])->name('fees.issue');
            Route::post('/groups/{group}/invoices', [FinanceController::class, 'issue'])
                ->whereNumber('group')->name('fees.issue.group');
            Route::post('/fees/collect', [FinanceController::class, 'collect'])->name('fees.collect');
            Route::post('/groups/{group}/invoices', [FinanceController::class, 'issue'])->name('fees.issue');
            Route::get('/cashboxes', [FinanceController::class, 'cashboxes'])->name('cashboxes');
            Route::post('/cashboxes/{cashbox}/close', [FinanceController::class, 'close'])->name('cashboxes.close');

            // الإنشاء قبل {id}، وإلا قُرئت «create» معرّفاً
            Route::get('/center-students/create', [StudentFileController::class, 'create'])->name('student.create');
            Route::post('/center-students', [StudentFileController::class, 'store'])->name('student.store');
            Route::post('/groups/{group}/enrol', [GroupEnrolmentController::class, 'store'])->name('enrol');
            Route::delete('/groups/{group}/enrol/{enrollment}', [GroupEnrolmentController::class, 'destroy'])->name('enrol.drop');
            Route::get('/center-students/{id}', [StudentFileController::class, 'show'])->name('student');
            // دعوةٌ جديدة: من أُنشئ حسابه بلا كلمة مرور يعرفها أحد يحتاج باباً
            Route::post('/center-students/{id}/invite', [StudentFileController::class, 'invite'])->name('student.invite');
            Route::get('/center-students/{id}/report', [StudentFileController::class, 'monthly'])->name('student.report');
        });

    // بوابة ولي الأمر
    Route::middleware('auth')->prefix('guardian')->name('guardian.')->group(function (): void {
        Route::get('/', [GuardianPortalController::class, 'index'])->name('index');
        Route::get('/children/{student}', [GuardianPortalController::class, 'child'])->name('child');
    });

    // إدارة الطلبات والأكواد — قبل مسار الموارد العام
    Route::prefix('admin')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class, EnsureAbility::class.':'.Ability::ORDERS_MANAGE])
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
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class, EnsureAbility::class.':'.Ability::GRADING])
        ->name('admin.grading.')->group(function (): void {
            Route::get('/', [GradingController::class, 'index'])->name('index');
            Route::get('/attempts/{attempt}', [GradingController::class, 'attempt'])->name('attempt');
            Route::put('/attempts/{attempt}/answers/{answer}', [GradingController::class, 'gradeAnswer'])->name('attempt.answer');
            Route::get('/submissions/{submission}', [GradingController::class, 'submission'])->name('submission');
            Route::put('/submissions/{submission}', [GradingController::class, 'gradeSubmission'])->name('submission.grade');
        });

    // باني المنهج — قبل مسار الموارد العام
    Route::prefix('admin/courses/{course}')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class, EnsureAbility::class.':'.Ability::CURRICULUM_MANAGE])
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
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class, EnsureAbility::class.':'.Ability::BILLING_MANAGE])
        ->name('admin.billing');

    /*
     | نمط المنصة — يُغيَّر بعد التسجيل لا في شاشته فقط.
     |
     | كان الاختيار يُقفل مرة واحدة، فمن أخطأ فيه بقي في لوحة ناقصة
     | بلا مخرج إلا حساب جديد.
     */
    Route::middleware([EnsurePanelAccess::class, RequireOnboarding::class, EnsureAbility::class.':'.Ability::SETTINGS_MANAGE])
        ->group(function (): void {
            // استهلاك الحدود — الحدّ الذي لا يُرى يُصطدَم به فجأةً
            Route::get('/admin/usage', UsageController::class)->name('admin.usage');

            /*
             | مفاتيح الواجهة البرمجية — محروسةٌ بالميزة والصلاحية.
             */
            // أجهزة الحضور — محروسةٌ بالميزة والصلاحية
            // توليد أسئلة من مادة — محروسٌ بالميزة والصلاحية والحدّ
            Route::get('/admin/ai/questions', [AiQuestionController::class, 'index'])->name('admin.ai.questions');
            Route::post('/admin/ai/questions', [AiQuestionController::class, 'store'])->name('admin.ai.questions.store');

            Route::get('/admin/devices', [AttendanceDeviceController::class, 'index'])->name('admin.center.devices');
            Route::post('/admin/devices', [AttendanceDeviceController::class, 'store'])->name('admin.center.devices.store');
            Route::delete('/admin/devices/{id}', [AttendanceDeviceController::class, 'destroy'])
                ->whereNumber('id')->name('admin.center.devices.destroy');

            Route::get('/admin/api', [ApiTokenController::class, 'index'])->name('admin.api');
            Route::post('/admin/api', [ApiTokenController::class, 'store'])->name('admin.api.store');
            Route::delete('/admin/api/{id}', [ApiTokenController::class, 'destroy'])
                ->whereNumber('id')->name('admin.api.destroy');

            /*
             | مرفقات الدرس شاشةٌ مستقلّة لا حقلٌ في نموذجه.
             |
             | للمرفق إعداداته (يُنزَّل؟ يُوسَم؟) وسجلّ فتحاته، وحشرُ
             | ذلك في نموذج الدرس يجعله صفحتين لا يُقرأ أيّهما.
             */
            /*
             | نقاط التفاعل داخل الفيديو.
             |
             | الطالب يشاهد عشرين دقيقة ثم ينتقل، ولا يعرف المدرّس
             | أفَهِم أم مرّت الصورة أمامه.
             */
            /*
             | المسار التكيّفي: قواعد تفريع المنهج بالنتيجة.
             */
            /*
             | حزم SCORM — رفعُها وتتبّع الطلبة فيها.
             */
            Route::prefix('admin/lessons/{lesson}/scorm')->whereNumber('lesson')
                ->name('admin.lessons.scorm.')->group(function (): void {
                    Route::get('/', [ScormController::class, 'index'])->name('index');
                    Route::post('/', [ScormController::class, 'store'])->name('store');
                    Route::delete('/', [ScormController::class, 'destroy'])->name('destroy');
                });

            Route::prefix('admin/courses/{course}/rules')->whereNumber('course')
                ->name('admin.courses.rules.')->group(function (): void {
                    Route::get('/', [LearningRuleController::class, 'index'])->name('index');
                    Route::post('/', [LearningRuleController::class, 'store'])->name('store');
                    Route::delete('/{id}', [LearningRuleController::class, 'destroy'])->whereNumber('id')->name('destroy');
                });

            Route::prefix('admin/lessons/{lesson}/moments')->whereNumber('lesson')
                ->name('admin.lessons.moments.')->group(function (): void {
                    Route::get('/', [VideoMomentController::class, 'index'])->name('index');
                    Route::post('/', [VideoMomentController::class, 'store'])->name('store');
                    Route::delete('/{id}', [VideoMomentController::class, 'destroy'])->whereNumber('id')->name('destroy');
                });

            Route::prefix('admin/lessons/{lesson}/attachments')->whereNumber('lesson')
                ->name('admin.lessons.attachments.')->group(function (): void {
                    Route::get('/', [LessonAttachmentController::class, 'index'])->name('index');
                    Route::post('/', [LessonAttachmentController::class, 'store'])->name('store');
                    Route::put('/{id}', [LessonAttachmentController::class, 'update'])->whereNumber('id')->name('update');
                    Route::delete('/{id}', [LessonAttachmentController::class, 'destroy'])->whereNumber('id')->name('destroy');
                });

            Route::get('/admin/platform-mode', [PlatformModeController::class, 'show'])->name('admin.platform-mode');
            Route::put('/admin/platform-mode', [PlatformModeController::class, 'update'])->name('admin.platform-mode.update');
        });

    // ‏/admin وحده كان يعطي ٤٠٤: صاحب اللوحة يكتب المسار الطبيعي لا الكامل
    Route::redirect('/admin', '/admin/dashboard');

    // الإعدادات — قبل مسار الموارد
    Route::prefix('admin/settings')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class, EnsureAbility::class.':'.Ability::SETTINGS_MANAGE.','.Ability::BILLING_MANAGE.','.Ability::APPEARANCE_MANAGE.','.Ability::MODULES_MANAGE.','.Ability::NOTIFICATIONS_MANAGE.','.Ability::AFFILIATES_MANAGE.','.Ability::GAMIFICATION_MANAGE])
        ->name('admin.settings.')->group(function (): void {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::get('/{group}', [SettingsController::class, 'show'])->name('show');
            Route::put('/{group}', [SettingsController::class, 'update'])->name('update');
        });

    // المحتوى: باني الصفحات والوسائط — قبل مسار الموارد العام
    Route::prefix('admin')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.')->group(function (): void {
            Route::middleware(EnsureAbility::class.':'.Ability::CONTENT_MANAGE)->group(function (): void {
                Route::get('/page-builder', [PageBuilderController::class, 'index'])->name('page-builder.index');
                Route::post('/page-builder', [PageBuilderController::class, 'store'])->name('page-builder.store');
                Route::get('/page-builder/{id}', [PageBuilderController::class, 'edit'])->name('page-builder.edit');
                Route::put('/page-builder/{id}', [PageBuilderController::class, 'update'])->name('page-builder.update');

                Route::put('/page-builder/{id}', [PageBuilderController::class, 'update'])->name('page-builder.update');
            });

            Route::middleware(EnsureAbility::class.':'.Ability::MEDIA_MANAGE)->group(function (): void {
                Route::get('/media', [MediaController::class, 'index'])->name('media.index');
                // قبل مسار {id}، وإلا قُرئت «browse» معرّفاً
                Route::get('/media/browse', [MediaController::class, 'browse'])->name('media.browse');
                Route::post('/media/upload', [MediaController::class, 'upload'])->name('media.upload');
                Route::post('/media', [MediaController::class, 'store'])->name('media.store');
                Route::put('/media/{id}', [MediaController::class, 'update'])->name('media.update');
                Route::delete('/media/{id}', [MediaController::class, 'destroy'])->name('media.destroy');
            });
        });

    // ---------- شاشات المدرّس ----------
    // قبل مسار الموارد العام، وإلا قُرئت «students» مورداً باسمها
    Route::prefix('admin')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.instructor.')->group(function (): void {
            Route::middleware(EnsureAbility::class.':'.Ability::STUDENTS_VIEW)->group(function (): void {
                Route::get('/students', [InstructorStudentController::class, 'index'])->name('students');
                Route::get('/students/{id}', [InstructorStudentController::class, 'show'])->name('student');
            });

            Route::middleware(EnsureAbility::class.':'.Ability::DISCUSSIONS_MODERATE)->group(function (): void {
                Route::get('/discussions', [InstructorDiscussionController::class, 'index'])->name('discussions');
                Route::get('/discussions/{id}', [InstructorDiscussionController::class, 'show'])->name('discussion');
                Route::post('/discussions/{id}/replies', [InstructorDiscussionController::class, 'reply'])->name('discussion.reply');
                Route::put('/discussions/{id}', [InstructorDiscussionController::class, 'update'])->name('discussion.update');
            });

            Route::middleware(EnsureAbility::class.':'.Ability::ANNOUNCEMENTS_MANAGE)->group(function (): void {
                Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements');
                Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
                Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
            });

            Route::middleware(EnsureAbility::class.':'.Ability::EARNINGS_VIEW)->group(function (): void {
                Route::get('/earnings', [EarningsController::class, 'index'])->name('earnings');
                Route::post('/earnings/payout', [EarningsController::class, 'request'])->name('earnings.payout');
            });

            Route::get('/statistics', StatisticsController::class)
                ->middleware(EnsureAbility::class.':'.Ability::STATISTICS_VIEW)
                ->name('statistics');
        });

    // التقارير — قبل مسار الموارد العام
    Route::prefix('admin/reports')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class, EnsureAbility::class.':'.Ability::REPORTS_VIEW])
        ->name('admin.reports.')->group(function (): void {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/export', [ReportController::class, 'export'])->name('export');
        });

    // النمو: المسوّقون والتسلسلات — قبل مسار الموارد العام
    Route::prefix('admin')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class])
        ->name('admin.')->group(function (): void {
            Route::middleware(EnsureAbility::class.':'.Ability::AFFILIATES_MANAGE)->group(function (): void {
                Route::get('/affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
                Route::put('/affiliates/{id}', [AffiliateController::class, 'update'])->name('affiliates.update');
                Route::put('/affiliate-conversions/{id}/reject', [AffiliateController::class, 'rejectConversion'])->name('affiliates.reject');
            });

            Route::middleware(EnsureAbility::class.':'.Ability::CAMPAIGNS_MANAGE)->group(function (): void {
                Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
                Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
                Route::get('/campaigns/{id}', [CampaignController::class, 'edit'])->name('campaigns.edit');
                Route::put('/campaigns/{id}', [CampaignController::class, 'update'])->name('campaigns.update');
            });
        });

    // مراجعة التقييمات — قبل مسار الموارد العام
    Route::prefix('admin/reviews')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class, EnsureAbility::class.':'.Ability::REVIEWS_MODERATE])
        ->name('admin.reviews.')->group(function (): void {
            Route::get('/', [ReviewController::class, 'queue'])->name('queue');
            Route::put('/{type}/{id}', [ReviewController::class, 'moderate'])->name('moderate');
        });

    // الإشعارات: المصفوفة والقوالب والسجلّ — قبل مسار الموارد العام
    Route::prefix('admin/notifications')
        ->middleware([EnsurePanelAccess::class, RequireOnboarding::class, EnsureAbility::class.':'.Ability::NOTIFICATIONS_MANAGE])
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

    /*
     | السيو: خريطة الموقع وrobots خارج بادئات اللغة.
     | الخريطة تحمل بدائل اللغات داخلها، وتكرارها لكل بادئة يضاعفها
     | بلا فائدة ويربك المحرّك في أي نسخة يُصدّق.
     */
    Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
    Route::get('/sitemap-{section}.xml', [SitemapController::class, 'section'])
        ->where('section', '[a-z]+')->name('sitemap.section');
    Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

    foreach (config('locales.prefixed') as $prefix) {
        Route::prefix($prefix)->name($prefix.'.')->group($tenantRoutes);
    }

    // آخر محطة قبل الـ404: جدول تحويلات الموقع القديم
    Route::fallback(RedirectController::class);
});
