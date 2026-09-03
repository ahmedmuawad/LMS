<?php

declare(strict_types=1);

use App\Core\Notifications\Jobs\SendNotification;
use App\Core\Support\Money;
use App\Modules\Commerce\Actions\RecordOrderPayment;
use App\Modules\Commerce\Actions\RefundOrder;
use App\Modules\Growth\Actions\DetectTriggers;
use App\Modules\Growth\Actions\RecordConversion;
use App\Modules\Growth\Actions\RunCampaigns;
use App\Modules\Growth\Actions\TrackAffiliate;
use App\Modules\Growth\Models\Affiliate;
use App\Modules\Growth\Models\AffiliateClick;
use App\Modules\Growth\Models\AffiliateConversion;
use App\Modules\Growth\Models\Campaign;
use App\Modules\Growth\Models\CampaignEnrolment;
use App\Modules\Growth\Models\CampaignSend;
use App\Modules\Growth\Models\CampaignStep;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

/*
 | التسويق بالعمولة.
 */

it('يسجّل النقرة ويضع كوكي النسب', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('growth.affiliates_enabled', true);
        seedAffiliate('promo1');
    });

    tenantGet($tenant, '/?ref=promo1')
        ->assertOk()
        ->assertCookie(TrackAffiliate::COOKIE);

    $tenant->run(function (): void {
        expect(AffiliateClick::count())->toBe(1)
            ->and((int) Affiliate::first()->clicks_count)->toBe(1);
    });
});

it('لا يسجّل نقرة لكود مجهول أو مسوّق موقوف', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('growth.affiliates_enabled', true);
        seedAffiliate('paused1', ['status' => 'suspended']);
    });

    tenantGet($tenant, '/?ref=nobody')->assertOk();
    tenantGet($tenant, '/?ref=paused1')->assertOk();

    $tenant->run(fn () => expect(AffiliateClick::count())->toBe(0));
});

it('لا يهرّب العنوان الخام في سجلّ النقرة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('growth.affiliates_enabled', true);
        seedAffiliate('promo1');
    });

    tenantGet($tenant, '/?ref=promo1');

    $tenant->run(function (): void {
        $click = AffiliateClick::firstOrFail();

        expect($click->ip_hash)->not->toBeNull()
            ->and($click->ip_hash)->not->toContain('127.0.0.1')
            ->and(strlen((string) $click->ip_hash))->toBe(64);
    });
});

it('يحتسب العمولة على الطلب بلا شحن ولا ضريبة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('growth.affiliates_enabled', true);

        $affiliate = seedAffiliate('promo1', ['commission_rate' => 10]);
        $order = seedPaidOrder(['total_minor' => 120000, 'tax_minor' => 10000, 'shipping_minor' => 10000]);

        $conversion = app(RecordConversion::class)->handle($order, $affiliate);

        // ١٢٠٠ − ١٠٠ ضريبة − ١٠٠ شحن = ١٠٠٠، وعمولتها ١٠٪ = ١٠٠
        expect((int) $conversion->amount_minor)->toBe(100000)
            ->and((int) $conversion->commission_minor)->toBe(10000)
            ->and($conversion->status)->toBe('pending');
    });
});

it('لا يعمّل المسوّق على شراء نفسه', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('growth.affiliates_enabled', true);

        $marketer = seedStudent('marketer@t.test');
        $affiliate = seedAffiliate('promo1', ['user_id' => $marketer->getKey()]);
        $order = seedPaidOrder(['user_id' => $marketer->getKey()]);

        expect(app(RecordConversion::class)->handle($order, $affiliate))->toBeNull()
            ->and(AffiliateConversion::count())->toBe(0);
    });
});

it('لا يحتسب الطلب نفسه مرتين', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('growth.affiliates_enabled', true);

        $affiliate = seedAffiliate('promo1');
        $order = seedPaidOrder();

        $first = app(RecordConversion::class)->handle($order, $affiliate);
        $second = app(RecordConversion::class)->handle($order, $affiliate);

        expect($second->getKey())->toBe($first->getKey())
            ->and(AffiliateConversion::count())->toBe(1);
    });
});

it('لا تنضج العمولة قبل انقضاء مهلة الاسترداد', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('growth.affiliates_enabled', true);
        setting()->set('growth.affiliates_hold_days', 14);

        $affiliate = seedAffiliate('promo1');
        app(RecordConversion::class)->handle($affiliate ? seedPaidOrder() : null, $affiliate);

        expect(app(RecordConversion::class)->matureAll())->toBe(0)
            ->and((int) $affiliate->refresh()->earned_minor)->toBe(0);

        AffiliateConversion::query()->update(['matured_at' => now()->subDay()]);

        expect(app(RecordConversion::class)->matureAll())->toBe(1)
            ->and((int) $affiliate->refresh()->earned_minor)->toBeGreaterThan(0);
    });
});

it('يسحب العمولة عند استرداد الطلب', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('growth.affiliates_enabled', true);

        $affiliate = seedAffiliate('promo1');
        $order = seedPaidOrder();

        app(RecordConversion::class)->handle($order, $affiliate);
        AffiliateConversion::query()->update(['matured_at' => now()->subDay()]);
        app(RecordConversion::class)->matureAll();

        $earned = (int) $affiliate->refresh()->earned_minor;
        expect($earned)->toBeGreaterThan(0);

        $refunds = app(RefundOrder::class);
        $refunds->approve($refunds->request($order, null, 'تجربة'));

        expect(AffiliateConversion::first()->status)->toBe('rejected')
            ->and((int) $affiliate->refresh()->earned_minor)->toBe(0);
    });
});

/*
 | التسلسلات التسويقية.
 */

it('يُدخل المستخدم مرة واحدة على الموضوع نفسه', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $campaign = seedCampaign();
        $user = seedStudent();

        app(RunCampaigns::class)->enrol($campaign, $user);
        app(RunCampaigns::class)->enrol($campaign, $user);

        expect(CampaignEnrolment::count())->toBe(1);
    });
});

it('لا يُدخل أحداً في تسلسل مسودّة أو بلا خطوات', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $draft = seedCampaign(['status' => 'draft']);
        $empty = Campaign::create(['key' => 'empty', 'name' => ['ar' => 'فارغ'], 'trigger' => 'manual', 'status' => 'active']);
        $user = seedStudent();

        expect(app(RunCampaigns::class)->enrol($draft, $user))->toBeNull()
            ->and(app(RunCampaigns::class)->enrol($empty, $user))->toBeNull();
    });
});

it('ينفّذ الخطوة عند حلول وقتها ويجدول التالية', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        setting()->set('notifications.from_email', 'no-reply@test.test');

        $campaign = seedCampaign();
        $user = seedStudent();

        $enrolment = app(RunCampaigns::class)->enrol($campaign, $user);
        $enrolment->forceFill(['next_step_at' => now()->subMinute()])->save();

        $result = app(RunCampaigns::class)->tick();

        expect($result['sent'])->toBe(1)
            ->and((int) $enrolment->refresh()->step_index)->toBe(1)
            ->and($enrolment->status)->toBe('running')
            ->and(CampaignSend::count())->toBe(1);
    });

    Queue::assertPushed(SendNotification::class);
});

it('لا يرسل الخطوة نفسها مرتين ولو أُعيد التنفيذ', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        $campaign = seedCampaign();
        $user = seedStudent();

        $enrolment = app(RunCampaigns::class)->enrol($campaign, $user);
        $enrolment->forceFill(['next_step_at' => now()->subMinute()])->save();

        app(RunCampaigns::class)->tick();

        // إعادة الفهرس كما لو تكرّر التنفيذ بعد تأخّر المهمة المجدولة
        $enrolment->forceFill(['step_index' => 0, 'status' => 'running', 'next_step_at' => now()->subMinute()])->save();
        app(RunCampaigns::class)->tick();

        expect(CampaignSend::count())->toBe(1);
    });
});

it('ينهي التسلسل بعد آخر خطوة', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        $campaign = seedCampaign();
        $user = seedStudent();

        $enrolment = app(RunCampaigns::class)->enrol($campaign, $user);

        foreach (range(1, 3) as $ignored) {
            $enrolment->refresh()->forceFill(['next_step_at' => now()->subMinute()])->save();
            app(RunCampaigns::class)->tick();
        }

        expect($enrolment->refresh()->status)->toBe('completed')
            ->and($enrolment->next_step_at)->toBeNull();
    });
});

it('يُخرج من حقّق الهدف فوراً', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $campaign = seedCampaign();
        $user = seedStudent();

        app(RunCampaigns::class)->enrol($campaign, $user);
        app(RunCampaigns::class)->convert($campaign, $user);

        expect(CampaignEnrolment::first()->status)->toBe('converted')
            ->and((int) $campaign->refresh()->converted_count)->toBe(1);
    });
});

it('يُخرج المشتري من حملة السلة المتروكة عند الدفع', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        $campaign = seedCampaign(['trigger' => 'cart_abandoned']);
        $order = seedPaidOrder(['status' => 'pending']);

        app(RunCampaigns::class)->enrol($campaign, $order->user);

        app(RecordOrderPayment::class)->handle(
            $order,
            Money::fromMinor((int) $order->total_minor, (string) $order->currency),
            'bank_transfer',
        );

        expect(CampaignEnrolment::first()->status)->toBe('converted');
    });
});

it('يرصد السلة المتروكة ويُدخل صاحبها', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('growth.cart_abandoned_after_minutes', 30);

        seedCampaign(['trigger' => 'cart_abandoned']);

        $user = seedStudent();
        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000]);
        $cart = seedCartFor($user, $course);

        // سلة لم تُلمس منذ ساعة
        $cart->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

        $entered = app(DetectTriggers::class)->handle();

        expect($entered['cart_abandoned'])->toBe(1)
            ->and(CampaignEnrolment::count())->toBe(1);
    });
});

it('لا يرصد سلة لُمست للتوّ', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('growth.cart_abandoned_after_minutes', 60);

        seedCampaign(['trigger' => 'cart_abandoned']);

        $user = seedStudent();
        seedCartFor($user, seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000]));

        expect(app(DetectTriggers::class)->handle()['cart_abandoned'])->toBe(0);
    });
});

/*
 | الشاشات.
 */

it('ينضمّ إلى برنامج المسوّقين ويرى رابطه', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('growth.affiliates_enabled', true);
        setting()->set('growth.affiliates_auto_approve', true);
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/affiliate')->assertOk()->assertSee('انضمّ إلى البرنامج');

    tenantPost($tenant, '/affiliate/join', ['payout_method' => 'bank'])->assertRedirect();

    $tenant->run(fn () => expect(Affiliate::first()->status)->toBe('active'));

    tenantGet($tenant, '/affiliate')->assertOk()->assertSee('رابطك');
});

it('يحجب صفحة المسوّقين حين يكون البرنامج معطّلاً', function (): void {
    $tenant = provision();

    actingAsOwner($tenant);

    tenantGet($tenant, '/affiliate')->assertNotFound();
});

it('يفتح لوحة المسوّقين ويغيّر حالة مسوّق', function (): void {
    $tenant = provision();

    $id = $tenant->run(function (): int {
        setting()->set('growth.affiliates_enabled', true);

        return (int) seedAffiliate('promo1', ['status' => 'pending'])->getKey();
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/affiliates')->assertOk()->assertSee('promo1');

    tenantPut($tenant, '/admin/affiliates/'.$id, ['status' => 'active', 'commission_rate' => 20])
        ->assertRedirect();

    $tenant->run(function (): void {
        $affiliate = Affiliate::firstOrFail();

        expect($affiliate->status)->toBe('active')
            ->and($affiliate->rate())->toBe(20.0)
            ->and($affiliate->approved_at)->not->toBeNull();
    });
});

it('ينشئ تسلسلاً ويحفظ خطواته ويحذف ما رُفع منها', function (): void {
    $tenant = provision();

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/campaigns')->assertOk();

    tenantPost($tenant, '/admin/campaigns', [
        'name' => 'استعادة السلة', 'key' => 'cart-recovery', 'trigger' => 'cart_abandoned',
    ])->assertRedirect();

    $id = $tenant->run(fn (): int => (int) Campaign::firstOrFail()->getKey());

    tenantPut($tenant, '/admin/campaigns/'.$id, [
        'name' => ['ar' => 'استعادة السلة'],
        'status' => 'active',
        'steps' => [
            ['delay_minutes' => 60, 'event' => 'commerce.abandoned_cart', 'is_active' => '1'],
            ['delay_minutes' => 1440, 'event' => 'commerce.abandoned_cart', 'is_active' => '1'],
            ['delay_minutes' => 60, 'event' => 'not.a.real.event', 'is_active' => '1'],
        ],
    ])->assertRedirect();

    $tenant->run(function () use ($id): void {
        // الحدث خارج الكتالوج لا يُحفظ ولو وصل في الطلب
        expect(CampaignStep::where('campaign_id', $id)->count())->toBe(2)
            ->and(Campaign::find($id)->status)->toBe('active');
    });

    // حفظ بخطوة واحدة يحذف الثانية: الخطوة المتروكة تُرسل
    tenantPut($tenant, '/admin/campaigns/'.$id, [
        'name' => ['ar' => 'استعادة السلة'],
        'status' => 'active',
        'steps' => [['delay_minutes' => 120, 'event' => 'commerce.abandoned_cart', 'is_active' => '1']],
    ])->assertRedirect();

    $tenant->run(fn () => expect(CampaignStep::where('campaign_id', $id)->count())->toBe(1));
});
