<?php

declare(strict_types=1);

use App\Core\Notifications\ChannelRegistry;
use App\Core\Notifications\EventCatalogue;
use App\Core\Notifications\Jobs\SendNotification;
use App\Core\Notifications\Models\Notification;
use App\Core\Notifications\Models\NotificationLog;
use App\Core\Notifications\Models\NotificationPreference;
use App\Core\Notifications\Models\NotificationTemplate;
use App\Core\Notifications\Models\PushSubscription;
use App\Core\Notifications\Notifier;
use App\Core\Notifications\TemplateRenderer;
use App\Models\User;
use App\Modules\Lms\Actions\EnrollStudent;
use Illuminate\Support\Facades\Queue;

/*
 | محرّك الإشعارات: الكتالوج والقوالب والتفضيلات وساعات الهدوء والسجلّ.
 */

it('يبني الكتالوج من config ويرفض حدثاً مجهولاً', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $catalogue = app(EventCatalogue::class);

        expect($catalogue->has('lms.enrolled'))->toBeTrue()
            ->and($catalogue->has('made.up.event'))->toBeFalse()
            ->and($catalogue->get('made.up.event'))->toBeNull();
    });
});

it('لا يُرسل حدثاً غير موجود في الكتالوج', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        $user = User::where('role', 'owner')->firstOrFail();

        expect(app(Notifier::class)->send('made.up.event', $user))->toBe([]);
    });

    Queue::assertNothingPushed();
});

it('يضع مهمة لكل قناة مفعّلة لا مهمة واحدة للحدث', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        setting()->set('notifications.from_email', 'no-reply@test.test');

        $user = User::where('role', 'owner')->firstOrFail();

        // lms.enrolled افتراضه البريد وداخل الموقع
        $queued = app(Notifier::class)->send('lms.enrolled', $user, ['course_title' => 'PHP']);

        expect($queued)->toContain('mail')->toContain('database');
    });

    Queue::assertPushed(SendNotification::class, 2);
});

it('يحترم إطفاء القناة من مصفوفة اللوحة', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        setting()->set('notifications.from_email', 'no-reply@test.test');

        NotificationTemplate::create([
            'event' => 'lms.enrolled', 'channel' => 'mail', 'is_enabled' => false,
        ]);

        $user = User::where('role', 'owner')->firstOrFail();
        $queued = app(Notifier::class)->send('lms.enrolled', $user);

        expect($queued)->not->toContain('mail')->toContain('database');
    });
});

it('يحترم تفضيل المستخدم فوق افتراض اللوحة', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        setting()->set('notifications.from_email', 'no-reply@test.test');

        $user = User::where('role', 'owner')->firstOrFail();

        NotificationPreference::create([
            'user_id' => $user->getKey(), 'event' => 'lms.enrolled',
            'channel' => 'database', 'is_enabled' => false,
        ]);

        expect(app(Notifier::class)->send('lms.enrolled', $user))->not->toContain('database');
    });
});

it('لا يسمح بإطفاء الحدث الأمني', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        setting()->set('notifications.from_email', 'no-reply@test.test');

        $user = User::where('role', 'owner')->firstOrFail();

        NotificationPreference::create([
            'user_id' => $user->getKey(), 'event' => 'account.password_changed',
            'channel' => 'mail', 'is_enabled' => false,
        ]);

        expect(app(Notifier::class)->send('account.password_changed', $user))->toContain('mail');
    });
});

it('لا يُرسل على قناة غير مُعدّة', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        // واتساب مطفأ افتراضياً — والحدث يسمح به
        $user = User::where('role', 'owner')->firstOrFail();

        expect(app(Notifier::class)->send('lms.enrolled', $user))->not->toContain('whatsapp');
    });
});

it('يؤجّل إلى ما بعد ساعات الهدوء ولا يُلغي', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        setting()->set('notifications.from_email', 'no-reply@test.test');
        setting()->set('notifications.quiet_hours', true);
        setting()->set('notifications.quiet_from', 0);
        setting()->set('notifications.quiet_to', 23);   // كل اليوم هدوء

        $user = User::where('role', 'owner')->firstOrFail();
        app(Notifier::class)->send('lms.enrolled', $user);
    });

    Queue::assertPushed(SendNotification::class, fn (SendNotification $job): bool => $job->delay !== null);
});

it('يستبدل المتغيّرات المعلَنة ويمحو ما ليس معلَناً', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $event = app(EventCatalogue::class)->get('lms.enrolled');

        $text = app(TemplateRenderer::class)->substitute(
            'أهلاً {{ first_name }} في {{ course_title }} — {{ secret_token }}',
            $event,
            ['first_name' => 'سارة', 'course_title' => 'PHP', 'secret_token' => 'ABC123'],
        );

        expect($text)->toContain('سارة')->toContain('PHP')
            ->and($text)->not->toContain('ABC123')
            ->and($text)->not->toContain('secret_token');
    });
});

it('يكتب نصّاً افتراضياً مفهوماً حين لا قالب', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $event = app(EventCatalogue::class)->get('lms.enrolled');

        $rendered = app(TemplateRenderer::class)->render($event, null, [
            'first_name' => 'سارة', 'site_name' => 'أكاديمية',
        ], 'ar');

        expect($rendered['subject'])->not->toBe('')
            ->and($rendered['body'])->toContain('سارة')
            ->and($rendered['body'])->toContain('أكاديمية');
    });
});

it('يكتب الإشعار في صندوق الوارد ويسجّله مُرسَلاً', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $user = User::where('role', 'owner')->firstOrFail();

        (new SendNotification('lms.enrolled', 'database', (int) $user->getKey(), [
            'course_title' => 'لارافيل', 'url' => '/learn/laravel',
        ]))->handle(
            app(EventCatalogue::class),
            app(ChannelRegistry::class),
            app(TemplateRenderer::class),
        );

        expect(Notification::count())->toBe(1)
            ->and(Notification::first()->url)->toBe('/learn/laravel')
            ->and(NotificationLog::where('status', 'sent')->count())->toBe(1);
    });
});

it('يسجّل تخطّياً حين لا عنوان للمستلم', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('notifications.sms_provider', 'twilio');
        setting()->set('notifications.sms_key', 'key');

        $user = User::create([
            'name' => 'بلا هاتف', 'email' => 'nophone@t.test',
            'password' => 'password', 'status' => 'active', 'role' => 'student',
        ]);

        (new SendNotification('account.reset_password', 'sms', (int) $user->getKey()))->handle(
            app(EventCatalogue::class),
            app(ChannelRegistry::class),
            app(TemplateRenderer::class),
        );

        expect(NotificationLog::where('status', 'skipped')->count())->toBe(1);
    });
});

it('يُخبر الطالب عند تسجيله في كورس', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        $student = seedStudent();
        $course = seedCourse();

        app(EnrollStudent::class)->handle($student, $course, 'manual');
    });

    Queue::assertPushed(SendNotification::class, fn (SendNotification $job): bool => $job->event === 'lms.enrolled');
});

it('يعرض صندوق الوارد ويعلّم الكل مقروءاً', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $user = User::where('role', 'owner')->firstOrFail();

        Notification::create([
            'user_id' => $user->getKey(), 'event' => 'lms.enrolled',
            'title' => 'سُجّلت في كورس', 'body' => 'ابدأ الآن.',
        ]);
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/notifications')->assertOk()->assertSee('سُجّلت في كورس');
    tenantPost($tenant, '/notifications/read-all')->assertRedirect();

    $tenant->run(fn () => expect(Notification::unread()->count())->toBe(0));
});

it('يحفظ مصفوفة الإشعارات من اللوحة', function (): void {
    $tenant = provision();

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/notifications')->assertOk()->assertSee('مصفوفة');

    tenantPut($tenant, '/admin/notifications', [
        'enabled' => ['lms.enrolled' => ['mail' => '1']],
    ])->assertRedirect();

    $tenant->run(function (): void {
        expect(NotificationTemplate::where('event', 'lms.enrolled')->where('channel', 'mail')->value('is_enabled'))->toBeTrue()
            ->and(NotificationTemplate::where('event', 'lms.enrolled')->where('channel', 'database')->value('is_enabled'))->toBeFalse();
    });
});

it('يحفظ قالب الحدث ويتجاهل قناة لا يسمح بها', function (): void {
    $tenant = provision();

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/notifications/lms.enrolled')->assertOk();

    tenantPut($tenant, '/admin/notifications/lms.enrolled', [
        'templates' => [
            'mail' => ['subject' => ['ar' => 'أهلاً'], 'body' => ['ar' => 'بدأت {{ course_title }}'], 'is_enabled' => '1'],
            'sms' => ['body' => ['ar' => 'محاولة تهريب'], 'is_enabled' => '1'],
        ],
    ])->assertRedirect();

    $tenant->run(function (): void {
        expect(NotificationTemplate::where('event', 'lms.enrolled')->where('channel', 'mail')->exists())->toBeTrue()
            // lms.enrolled لا يسمح بالرسائل النصية
            ->and(NotificationTemplate::where('event', 'lms.enrolled')->where('channel', 'sms')->exists())->toBeFalse();
    });
});

it('يفتح شاشة التفضيلات ويحفظها', function (): void {
    $tenant = provision();

    $tenant->run(fn () => setting()->set('notifications.from_email', 'no-reply@test.test'));

    actingAsOwner($tenant);

    tenantGet($tenant, '/account/notifications')->assertOk();

    tenantPut($tenant, '/account/notifications', [
        'enabled' => ['lms.enrolled' => ['database' => '1']],
    ])->assertRedirect();

    $tenant->run(function (): void {
        $user = User::where('role', 'owner')->firstOrFail();

        expect(NotificationPreference::where('user_id', $user->getKey())
            ->where('event', 'lms.enrolled')->where('channel', 'mail')->value('is_enabled'))->toBeFalse();
    });
});

it('يسجّل جهازاً لإشعارات المتصفّح ويحذفه', function (): void {
    $tenant = provision();

    actingAsOwner($tenant);

    $endpoint = 'https://push.example.test/abc';

    tenantPost($tenant, '/account/push', [
        'endpoint' => $endpoint,
        'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
    ])->assertOk();

    $tenant->run(fn () => expect(PushSubscription::count())->toBe(1));

    tenantDelete($tenant, '/account/push', ['endpoint' => $endpoint])->assertOk();

    $tenant->run(fn () => expect(PushSubscription::count())->toBe(0));
});

it('يعرض سجلّ الإرسال في اللوحة', function (): void {
    $tenant = provision();

    $tenant->run(fn () => NotificationLog::create([
        'event' => 'lms.enrolled', 'channel' => 'mail',
        'status' => 'failed', 'reason' => 'خطأ تجريبي',
    ]));

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/notifications/logs')->assertOk()->assertSee('خطأ تجريبي');
});

/*
 | تطبيق الويب التقدّمي.
 */

it('يولّد بيان التطبيق باسم المشترك ولونه', function (): void {
    $tenant = provision();

    $tenant->run(fn () => setting()->set('appearance.brand_color', '#7c3aed'));

    tenantGet($tenant, '/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('name', 'أكاديمية الاختبار');
});

it('يقدّم عامل الخدمة من الجذر بنطاق الموقع كلّه', function (): void {
    $tenant = provision();

    tenantGet($tenant, '/service-worker.js')
        ->assertOk()
        ->assertHeader('Service-Worker-Allowed', '/')
        ->assertSee('notificationclick', false);
});

it('يولّد أيقونة بدرجة لون مقروءة مع الأبيض', function (): void {
    $tenant = provision();

    // لون فاتح جداً: الأيقونة يجب أن تُغمّقه لا أن تستعمله كما هو
    $tenant->run(fn () => setting()->set('appearance.brand_color', '#ffff00'));

    $response = tenantGet($tenant, '/icon.svg')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('image/svg+xml')
        ->and($response->getContent())->not->toContain('#ffff00');
});

it('يعرض صفحة بلا اتصال', function (): void {
    $tenant = provision();

    tenantGet($tenant, '/offline')->assertOk()->assertSee('لا اتصال بالإنترنت');
});
