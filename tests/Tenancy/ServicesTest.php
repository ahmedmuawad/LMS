<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Services\Actions\BookService;
use App\Modules\Services\Actions\FindSlots;
use App\Modules\Services\Models\AvailabilityException;
use App\Modules\Services\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 | الخدمات: المواعيد المتاحة والحجز والتعارض والإلغاء.
 */

it('يبني المواعيد من ساعات العمل بمدّة الخدمة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $service = seedService(['duration_minutes' => 60, 'buffer_minutes' => 0]);

        $calendar = app(FindSlots::class)->handle($service, now()->addDay()->startOfDay(), 1);

        $day = array_values($calendar)[0];

        // ٩ صباحاً إلى ٥ مساءً بجلسة ساعة = ثمانية مواعيد
        expect($day)->toHaveCount(8)
            ->and($day[0]['starts_at'])->toBe('09:00')
            ->and($day[7]['starts_at'])->toBe('16:00');
    });
});

it('يحترم الفاصل بين الجلسات', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $service = seedService(['duration_minutes' => 60, 'buffer_minutes' => 30]);

        $day = array_values(app(FindSlots::class)->handle($service, now()->addDay()->startOfDay(), 1))[0];

        expect($day[0]['starts_at'])->toBe('09:00')
            ->and($day[1]['starts_at'])->toBe('10:30');
    });
});

it('لا يعرض موعداً أقرب من مهلة الحجز', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $service = seedService(['lead_hours' => 72]);

        $calendar = app(FindSlots::class)->handle($service, now(), 2);

        expect($calendar)->toBe([]);
    });
});

it('يُسقط المواعيد المحجوزة من التقويم', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $service = seedService();
        $date = now()->addDay()->startOfDay();

        app(BookService::class)->handle($service, [
            'name' => 'عميل', 'email' => 'c@t.test',
            'provider_id' => $service->providers()->first()->getKey(),
            'date' => $date->toDateString(),
            'starts_at' => '09:00',
        ]);

        $day = array_values(app(FindSlots::class)->handle($service, $date, 1))[0];
        $times = array_column($day, 'starts_at');

        expect($times)->not->toContain('09:00')
            ->and($times)->toContain('10:00');
    });
});

it('يُلغي اليوم كاملاً عند الإجازة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $service = seedService();
        $date = now()->addDay()->startOfDay();

        AvailabilityException::create([
            'provider_id' => $service->providers()->first()->getKey(),
            'date' => $date->toDateString(),
            'is_available' => false,
            'reason' => 'إجازة',
        ]);

        expect(app(FindSlots::class)->handle($service, $date, 1))->toBe([]);
    });
});

it('يرفض حجز موعد سبقه غيره إليه', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $service = seedService();
        $provider = $service->providers()->first();
        $date = now()->addDay()->toDateString();

        $input = [
            'name' => 'عميل', 'email' => 'c@t.test',
            'provider_id' => $provider->getKey(),
            'date' => $date, 'starts_at' => '09:00',
        ];

        app(BookService::class)->handle($service, $input);

        expect(fn () => app(BookService::class)->handle($service, $input))
            ->toThrow(RuntimeException::class);

        expect(Booking::count())->toBe(1);
    });
});

it('يسمح بحجزين في الموعد نفسه حين تسمح السعة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $service = seedService(['max_per_slot' => 2]);
        $provider = $service->providers()->first();

        $input = [
            'name' => 'عميل', 'email' => 'c@t.test',
            'provider_id' => $provider->getKey(),
            'date' => now()->addDay()->toDateString(), 'starts_at' => '09:00',
        ];

        app(BookService::class)->handle($service, $input);
        app(BookService::class)->handle($service, $input);

        expect(Booking::count())->toBe(2);
    });
});

it('يرفض حجز خدمة غير منشورة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $service = seedService(['status' => 'draft']);

        expect(fn () => app(BookService::class)->handle($service, ['name' => 'عميل']))
            ->toThrow(RuntimeException::class);
    });
});

it('يرقّم الحجوزات تسلسلياً بلا تكرار', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $service = seedService(['type' => 'delivery', 'max_per_slot' => 5]);

        $first = app(BookService::class)->handle($service, ['name' => 'أ', 'email' => 'a@t.test']);
        $second = app(BookService::class)->handle($service, ['name' => 'ب', 'email' => 'b@t.test']);

        expect($first->reference)->toEndWith('0001')
            ->and($second->reference)->toEndWith('0002')
            ->and($first->reference)->not->toBe($second->reference);
    });
});

it('يمنع الإلغاء بعد انتهاء المهلة المجانية', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $service = seedService(['cancel_hours' => 48]);

        $booking = Booking::create([
            'reference' => 'BK-TEST-0001',
            'service_id' => $service->getKey(),
            'date' => now()->addHours(12)->toDateString(),
            'starts_at' => now()->addHours(12)->format('H:i:s'),
            'ends_at' => now()->addHours(13)->format('H:i:s'),
            'status' => 'confirmed',
            'currency' => $service->currency,
            'price_minor' => $service->price_minor,
        ]);

        expect($booking->canCancelFreely())->toBeFalse();
    });
});

it('يعرض صفحة الخدمة ويحجز منها ثم يُظهر تفاصيل الحجز', function (): void {
    $tenant = provision();
    $service = $tenant->run(fn () => seedService());

    tenantGet($tenant, '/services')->assertOk()->assertSee('استشارة');
    tenantGet($tenant, '/services/'.$service->slug)->assertOk()->assertSee('أكّد الحجز');

    $providerId = $tenant->run(fn () => $service->providers()->first()->getKey());
    $date = Carbon::tomorrow()->toDateString();

    tenantPost($tenant, '/services/'.$service->slug.'/book', [
        'name' => 'سارة',
        'email' => 'sara@t.test',
        'provider_id' => $providerId,
        'date' => $date,
        'starts_at' => '09:00',
    ])->assertRedirect();

    $booking = $tenant->run(fn () => Booking::firstOrFail());

    tenantGet($tenant, '/bookings/'.$booking->token)->assertOk()->assertSee($booking->reference);
});

it('لا يفتح الحجز برقمه المتسلسل لغير أصحاب اللوحة', function (): void {
    $tenant = provision();

    $booking = $tenant->run(function (): Booking {
        $service = seedService();

        return app(BookService::class)->handle($service, [
            'name' => 'ضيف', 'email' => 'guest@t.test',
            'provider_id' => $service->providers()->first()->getKey(),
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '09:00',
        ]);
    });

    // الرقم متسلسل: لو فتح الحجز لصار تصفّح بيانات الناس عدّاً تصاعدياً
    tenantGet($tenant, '/bookings/'.$booking->reference)->assertNotFound();
    tenantGet($tenant, '/bookings/'.$booking->token)->assertOk();
});

it('يؤكّد الحجز فوراً حين يختار المشترك التأكيد التلقائي', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('services.confirmation', 'auto');

        $service = seedService();

        $booking = app(BookService::class)->handle($service, [
            'name' => 'عميل', 'email' => 'c@t.test',
            'provider_id' => $service->providers()->first()->getKey(),
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '09:00',
        ]);

        expect($booking->status)->toBe('confirmed')
            ->and($booking->confirmed_at)->not->toBeNull();
    });
});

it('يمنع تجاوز حدّ الحجوزات المفتوحة للعميل', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('services.max_open_per_user', 1);

        $service = seedService(['max_per_slot' => 5, 'type' => 'delivery']);
        $user = User::where('role', 'owner')->firstOrFail();

        app(BookService::class)->handle($service, ['name' => 'أ'], $user);

        expect(fn () => app(BookService::class)->handle($service, ['name' => 'ب'], $user))
            ->toThrow(RuntimeException::class);
    });
});

it('يعرض للعميل حجوزاته وحدها', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $service = seedService(['type' => 'delivery', 'max_per_slot' => 5]);

        app(BookService::class)->handle($service, ['name' => 'ضيف', 'email' => 'g@t.test']);
        app(BookService::class)->handle(
            $service,
            ['name' => 'المالك'],
            User::where('role', 'owner')->firstOrFail(),
        );
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/my-bookings')->assertOk()->assertSee('BK-');
});

it('يقرأ التقويم باستعلامات معدودة مهما كثرت المواعيد', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $service = seedService();
        $provider = $service->providers()->first();

        foreach (range(1, 5) as $day) {
            Booking::create([
                'reference' => 'BK-SEED-000'.$day,
                'service_id' => $service->getKey(),
                'provider_id' => $provider->getKey(),
                'date' => now()->addDays($day)->toDateString(),
                'starts_at' => '09:00:00', 'ends_at' => '10:00:00',
                'status' => 'confirmed',
                'currency' => $service->currency, 'price_minor' => 0,
            ]);
        }

        DB::enableQueryLog();
        app(FindSlots::class)->handle($service, now(), 14);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // مقدّمون + محجوز + إعدادات — لا استعلام لكل موعد
        expect($queries)->toBeLessThan(10);
    });
});

it('يمنع عميلاً من فتح حجز غيره', function (): void {
    $tenant = provision();

    $booking = $tenant->run(function (): Booking {
        $service = seedService();
        $owner = User::where('role', 'owner')->firstOrFail();

        return app(BookService::class)->handle($service, [
            'provider_id' => $service->providers()->first()->getKey(),
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '09:00',
        ], $owner);
    });

    $intruder = $tenant->run(fn () => User::create([
        'name' => 'دخيل', 'email' => 'intruder@t.test',
        'password' => 'password', 'status' => 'active', 'role' => 'student',
    ]));

    tenancy()->initialize($tenant);
    test()->actingAs($intruder);

    tenantGet($tenant, '/bookings/'.$booking->token)->assertForbidden();
});
