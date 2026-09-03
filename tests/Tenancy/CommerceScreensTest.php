<?php

declare(strict_types=1);

use App\Modules\Commerce\Actions\CartManager;
use App\Modules\Commerce\Actions\GenerateCodes;
use App\Modules\Commerce\Actions\PlaceOrder;
use App\Modules\Commerce\Actions\RecordOrderPayment;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Payment;
use App\Modules\Commerce\Models\RechargeCode;
use App\Modules\Commerce\Models\WalletTransaction;
use App\Modules\Lms\Models\Enrollment;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

it('shows an empty cart with a way out, not a dead end', function () {
    $tenant = provision();

    tenantGet($tenant, '/cart')
        ->assertOk()
        ->assertSee('سلتك فارغة')
        ->assertSee('تصفّح الكورسات');
});

it('adds a paid course to the cart straight from its page', function () {
    $tenant = provision();
    $courseId = $tenant->run(fn (): int => seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000])->id);

    tenantPost($tenant, '/cart/add', ['course_id' => $courseId])->assertRedirect();

    $tenant->run(fn () => expect(Cart::first()->items()->count())->toBe(1));
});

it('carries a guest cart over when they sign in', function () {
    $tenant = provision();

    [$courseId, $student] = $tenant->run(fn (): array => [
        seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000])->id,
        seedStudent(),
    ]);

    // زائر يضيف إلى سلته، فيحمل الرمز في كوكي
    $token = tenantPost($tenant, '/cart/add', ['course_id' => $courseId])
        ->getCookie(CartManager::COOKIE)?->getValue();

    expect($token)->not->toBeNull();

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    $this->withCookie(CartManager::COOKIE, $token)
        ->get(tenantUrl($tenant, '/cart'))
        ->assertOk();

    $tenant->run(fn () => expect(Cart::first()->user_id)->toBe($student->id));
});

it('shows the checkout with the gateways that are actually configured', function () {
    $tenant = provision();

    $student = $tenant->run(function () {
        setting()->set('payments.bank_transfer_enabled', true);
        setting()->set('payments.bank_transfer_currencies', ['EGP']);
        setting()->set('payments.stripe_enabled', false);

        return seedStudent();
    });

    $courseId = $tenant->run(fn (): int => seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000])->id);

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    tenantPost($tenant, '/cart/add', ['course_id' => $courseId]);

    tenantGet($tenant, '/checkout')
        ->assertOk()
        ->assertSee('تحويل بنكي')
        ->assertDontSee('بطاقة ائتمان دولية');
});

it('places an order and shows the bank instructions', function () {
    $tenant = provision();

    $student = $tenant->run(function () {
        setting()->set('payments.bank_transfer_enabled', true);
        setting()->set('payments.bank_transfer_currencies', ['EGP']);
        setting()->set('payments.bank_transfer_instructions', 'حوّل إلى حساب رقم 123 ثم أرسل الإيصال.');

        return seedStudent();
    });

    $courseId = $tenant->run(fn (): int => seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000])->id);

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    tenantPost($tenant, '/cart/add', ['course_id' => $courseId]);
    tenantPost($tenant, '/checkout', ['gateway' => 'bank_transfer'])->assertRedirect();

    $number = $tenant->run(fn (): string => Order::firstOrFail()->number);

    tenantGet($tenant, '/orders/'.$number)
        ->assertOk()
        ->assertSee('حوّل إلى حساب رقم 123');
});

it('refuses to sell to a guest when guest checkout is off', function () {
    $tenant = provision();
    $courseId = $tenant->run(function (): int {
        setting()->set('commerce.guest_checkout', false);
        setting()->set('payments.bank_transfer_enabled', true);

        return seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000])->id;
    });

    $token = tenantPost($tenant, '/cart/add', ['course_id' => $courseId])
        ->getCookie(CartManager::COOKIE)?->getValue();

    $this->withCookie(CartManager::COOKIE, $token)
        ->post(tenantUrl($tenant, '/checkout'), ['gateway' => 'bank_transfer', 'email' => 'g@example.test'])
        ->assertRedirectContains('/login');
});

it('keeps one buyer order away from another', function () {
    $tenant = provision();

    [$number, $intruder] = $tenant->run(function (): array {
        $buyer = seedStudent('buyer@example.test');
        $cart = Cart::create(['token' => (string) Str::uuid(), 'currency' => 'EGP', 'user_id' => $buyer->id]);
        app(CartManager::class)->add($cart, seedProduct());
        $order = app(PlaceOrder::class)->handle($cart->refresh(), $buyer);

        return [$order->number, seedStudent('intruder@example.test')];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($intruder);

    tenantGet($tenant, '/orders/'.$number)->assertForbidden();
});

it('tops up a wallet from the student screen', function () {
    $tenant = provision();

    [$student, $code] = $tenant->run(function (): array {
        $batch = app(GenerateCodes::class)->handle([
            'name' => 'كروت', 'quantity' => 1, 'type' => 'wallet', 'value_minor' => 25000,
        ]);

        return [seedStudent(), RechargeCode::where('batch_id', $batch->id)->value('code')];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    tenantPost($tenant, '/wallet/redeem', ['code' => $code])->assertRedirect();

    tenantGet($tenant, '/wallet')->assertOk()->assertSee('250.00');

    $tenant->run(fn () => expect(WalletTransaction::balanceFor((int) $student->id, 'EGP')->minor)->toBe(25000));
});

it('tells the student plainly that a code was already used', function () {
    $tenant = provision();

    [$student, $code] = $tenant->run(function (): array {
        $student = seedStudent();
        $record = RechargeCode::create([
            'code' => 'AAAA-BBBB-CCCC-DDDD', 'type' => 'wallet',
            'currency' => 'EGP', 'value_minor' => 5000, 'status' => 'used',
        ]);

        return [$student, $record->code];
    });

    tenancy()->initialize($tenant);
    $this->actingAs($student);

    tenantPost($tenant, '/wallet/redeem', ['code' => $code])
        ->assertSessionHasErrors(['code' => 'استُخدم هذا الكود من قبل.']);
});

// ------------------------------------------------------------------
// شاشات اللوحة
// ------------------------------------------------------------------

it('opens every commerce admin screen for the owner', function () {
    $tenant = provision();
    $tenant->run(fn () => seedProduct());
    actingAsOwner($tenant);

    foreach (['orders', 'products', 'coupons', 'recharge-codes', 'refunds'] as $resource) {
        tenantGet($tenant, '/admin/'.$resource)->assertOk();
    }

    tenantGet($tenant, '/admin/recharge-codes/generate')->assertOk();
});

it('records a manual bank payment from the order screen and opens the course', function () {
    $tenant = provision();

    [$orderId, $studentId, $courseId] = $tenant->run(function (): array {
        $student = seedStudent();
        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000]);
        $cart = Cart::create(['token' => (string) Str::uuid(), 'currency' => 'EGP', 'user_id' => $student->id]);
        app(CartManager::class)->add($cart, productForCourse($course));
        $order = app(PlaceOrder::class)->handle($cart->refresh(), $student);

        return [$order->id, $student->id, $course->id];
    });

    actingAsOwner($tenant);

    tenantGet($tenant, '/admin/orders/'.$orderId)->assertOk();

    tenantPost($tenant, '/admin/orders/'.$orderId.'/pay', [
        'amount' => '500.00',
        'gateway' => 'bank_transfer',
        'reference' => 'TRX-77',
    ])->assertRedirect();

    $tenant->run(function () use ($studentId, $courseId): void {
        expect(Enrollment::where('user_id', $studentId)->where('course_id', $courseId)->exists())->toBeTrue();
    });
});

it('refuses to cancel an order that was already paid', function () {
    $tenant = provision();

    $orderId = $tenant->run(function (): int {
        $cart = Cart::create(['token' => (string) Str::uuid(), 'currency' => 'EGP']);
        app(CartManager::class)->add($cart, seedProduct());
        $order = app(PlaceOrder::class)->handle($cart->refresh(), null, ['email' => 'g@example.test']);
        app(RecordOrderPayment::class)->handle($order, $order->total(), 'cash');

        return $order->id;
    });

    actingAsOwner($tenant);

    tenantPut($tenant, '/admin/orders/'.$orderId.'/cancel')->assertStatus(409);
});

it('generates a batch of codes from the admin screen', function () {
    $tenant = provision();
    actingAsOwner($tenant);

    tenantPost($tenant, '/admin/recharge-codes/generate', [
        'name' => 'كروت أكتوبر',
        'quantity' => 25,
        'type' => 'wallet',
        'value' => '50',
    ])->assertRedirect();

    $tenant->run(fn () => expect(RechargeCode::count())->toBe(25));
});

it('will not generate a course code without naming a course', function () {
    $tenant = provision();
    actingAsOwner($tenant);

    tenantPost($tenant, '/admin/recharge-codes/generate', [
        'name' => 'كروت', 'quantity' => 5, 'type' => 'course',
    ])->assertSessionHasErrors('course_id');

    $tenant->run(fn () => expect(RechargeCode::count())->toBe(0));
});

it('rejects a webhook that carries no valid signature', function () {
    $tenant = provision();

    $tenant->run(fn () => setting()->set('payments.paymob_hmac_secret', 'real-secret'));

    tenantPost($tenant, '/webhooks/payments/paymob', ['obj' => ['success' => true]])
        ->assertStatus(202);

    $tenant->run(fn () => expect(Payment::count())->toBe(0));
});

it('answers an unknown gateway webhook with a plain 404', function () {
    $tenant = provision();

    tenantPost($tenant, '/webhooks/payments/not-a-gateway', [])->assertNotFound();
});
