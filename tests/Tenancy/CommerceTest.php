<?php

declare(strict_types=1);

use App\Core\Support\Money;
use App\Models\User;
use App\Modules\Commerce\Actions\ApplyCoupon;
use App\Modules\Commerce\Actions\CartManager;
use App\Modules\Commerce\Actions\CreatePayout;
use App\Modules\Commerce\Actions\GenerateCodes;
use App\Modules\Commerce\Actions\OrderNumber;
use App\Modules\Commerce\Actions\PlaceOrder;
use App\Modules\Commerce\Actions\RecordOrderPayment;
use App\Modules\Commerce\Actions\RedeemCode;
use App\Modules\Commerce\Actions\RefundOrder;
use App\Modules\Commerce\Gateways\Drivers\WalletGateway;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\InstructorEarning;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\RechargeCode;
use App\Modules\Commerce\Models\WalletTransaction;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Instructor;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

function makeCart(): Cart
{
    return Cart::create([
        'token' => (string) Str::uuid(),
        'currency' => (string) (tenant('currency') ?? 'EGP'),
    ]);
}

// ------------------------------------------------------------------
// المنتج يتبع الكورس
// ------------------------------------------------------------------

it('creates a sellable product the moment a course is published', function () {
    provision()->run(function (): void {
        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 149900]);

        $product = productForCourse($course);

        expect($product->status)->toBe('published')
            ->and($product->price_minor)->toBe(149900)
            ->and($product->type)->toBe('course');
    });
});

it('follows the course price without anyone remembering to', function () {
    provision()->run(function (): void {
        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 10000]);

        $course->forceFill(['price_minor' => 25000])->save();

        expect(productForCourse($course)->refresh()->price_minor)->toBe(25000);
    });
});

it('takes the product off the shelf when the course is unpublished', function () {
    provision()->run(function (): void {
        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 10000]);

        $course->forceFill(['status' => 'draft'])->save();

        expect(productForCourse($course)->refresh()->status)->toBe('archived');
    });
});

// ------------------------------------------------------------------
// السلة
// ------------------------------------------------------------------

it('refuses to mix two currencies in one cart', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        $product = seedProduct(['currency' => 'USD']);

        expect(fn () => app(CartManager::class)->add($cart, $product))
            ->toThrow(RuntimeException::class);
    });
});

it('never sells the same course twice in one cart', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 10000]);
        $product = productForCourse($course);

        app(CartManager::class)->add($cart, $product, 3);
        $item = app(CartManager::class)->add($cart, $product, 5);

        expect($item->quantity)->toBe(1)
            ->and($cart->refresh()->items()->count())->toBe(1);
    });
});

it('does add up quantity for a physical product', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        $product = seedProduct(['type' => 'physical', 'requires_shipping' => true]);

        app(CartManager::class)->add($cart, $product, 2);
        $item = app(CartManager::class)->add($cart, $product, 3);

        expect($item->quantity)->toBe(5);
    });
});

it('refuses to sell what is out of stock', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        $product = seedProduct(['type' => 'physical', 'manage_stock' => true, 'stock_qty' => 1]);

        expect(fn () => app(CartManager::class)->add($cart, $product, 5))
            ->toThrow(RuntimeException::class);
    });
});

// ------------------------------------------------------------------
// الكوبونات
// ------------------------------------------------------------------

it('applies a percentage discount', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        app(CartManager::class)->add($cart, seedProduct(['price_minor' => 100000]));
        app(ApplyCoupon::class)->handle($cart, seedCoupon(['value' => 25])->code);

        expect(app(CartManager::class)->totals($cart->refresh())['discount']->minor)->toBe(25000);
    });
});

it('caps the discount at the ceiling the merchant set', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        app(CartManager::class)->add($cart, seedProduct(['price_minor' => 1000000]));
        app(ApplyCoupon::class)->handle($cart, seedCoupon(['value' => 50, 'max_discount_minor' => 10000])->code);

        expect(app(CartManager::class)->totals($cart->refresh())['discount']->minor)->toBe(10000);
    });
});

it('never turns a discount into store credit', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        app(CartManager::class)->add($cart, seedProduct(['price_minor' => 5000]));
        app(ApplyCoupon::class)->handle($cart, seedCoupon(['type' => 'fixed', 'value' => 500])->code);

        $totals = app(CartManager::class)->totals($cart->refresh());

        expect($totals['discount']->minor)->toBe(5000)
            ->and($totals['total']->isZero())->toBeTrue();
    });
});

it('explains why a coupon is refused instead of saying it is invalid', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        app(CartManager::class)->add($cart, seedProduct());

        $expired = seedCoupon(['ends_at' => now()->subDay()]);
        $unstarted = seedCoupon(['starts_at' => now()->addWeek()]);
        $exhausted = seedCoupon(['usage_limit' => 1, 'used_count' => 1]);

        expect(fn () => app(ApplyCoupon::class)->handle($cart, $expired->code))
            ->toThrow(RuntimeException::class, 'انتهت صلاحية هذا الكود.')
            ->and(fn () => app(ApplyCoupon::class)->handle($cart, $unstarted->code))
            ->toThrow(RuntimeException::class, 'لم يبدأ سريان هذا الكود بعد.')
            ->and(fn () => app(ApplyCoupon::class)->handle($cart, $exhausted->code))
            ->toThrow(RuntimeException::class, 'استُنفد هذا الكود.');
    });
});

it('holds a coupon to its minimum order', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        app(CartManager::class)->add($cart, seedProduct(['price_minor' => 5000]));

        expect(fn () => app(ApplyCoupon::class)->handle($cart, seedCoupon(['min_order_minor' => 100000])->code))
            ->toThrow(RuntimeException::class);
    });
});

// ------------------------------------------------------------------
// الطلب
// ------------------------------------------------------------------

it('numbers orders in an unbroken sequence', function () {
    provision()->run(function (): void {
        setting()->set('commerce.order_prefix', 'ORD-');
        setting()->set('commerce.order_start', 1000);

        $first = OrderNumber::next();
        Order::create(['number' => $first, 'currency' => 'EGP', 'status' => 'pending']);

        expect($first)->toBe('ORD-1000')
            ->and(OrderNumber::next())->toBe('ORD-1001');
    });
});

it('freezes the title and price into the order line', function () {
    provision()->run(function (): void {
        $student = seedStudent();
        $cart = makeCart();
        $cart->forceFill(['user_id' => $student->id])->save();

        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000]);
        app(CartManager::class)->add($cart, productForCourse($course));

        $order = app(PlaceOrder::class)->handle($cart->refresh(), $student);

        $course->forceFill(['title' => ['ar' => 'اسم مختلف تماماً'], 'price_minor' => 999900])->save();

        $item = $order->items()->firstOrFail();

        expect($item->title_snapshot['ar'])->toBe('أساسيات لارافيل')
            ->and($item->unit_price_minor)->toBe(50000);
    });
});

it('empties the cart once the order is placed', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        app(CartManager::class)->add($cart, seedProduct());

        app(PlaceOrder::class)->handle($cart->refresh(), null, ['email' => 'guest@example.test']);

        expect($cart->refresh()->items()->count())->toBe(0);
    });
});

it('refuses to place an empty order', function () {
    provision()->run(function (): void {
        expect(fn () => app(PlaceOrder::class)->handle(makeCart(), null))
            ->toThrow(RuntimeException::class);
    });
});

it('counts the coupon usage against the buyer', function () {
    provision()->run(function (): void {
        $student = seedStudent();
        $cart = makeCart();
        $cart->forceFill(['user_id' => $student->id])->save();

        app(CartManager::class)->add($cart, seedProduct(['price_minor' => 100000]));
        $coupon = seedCoupon(['value' => 10]);
        app(ApplyCoupon::class)->handle($cart, $coupon->code);

        app(PlaceOrder::class)->handle($cart->refresh(), $student);

        expect($coupon->refresh()->used_count)->toBe(1);

        $second = makeCart();
        $second->forceFill(['user_id' => $student->id])->save();
        app(CartManager::class)->add($second, seedProduct(['price_minor' => 100000]));

        expect(fn () => app(ApplyCoupon::class)->handle($second, $coupon->code))
            ->toThrow(RuntimeException::class, 'استخدمت هذا الكود من قبل.');
    });
});

it('drops stock as the order is placed', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        $product = seedProduct(['type' => 'physical', 'manage_stock' => true, 'stock_qty' => 10]);

        app(CartManager::class)->add($cart, $product, 3);
        app(PlaceOrder::class)->handle($cart->refresh(), null, ['email' => 'g@example.test']);

        expect($product->refresh()->stock_qty)->toBe(7);
    });
});

// ------------------------------------------------------------------
// الدفع والتسليم
// ------------------------------------------------------------------

it('opens the course the moment the payment lands', function () {
    provision()->run(function (): void {
        $student = seedStudent();
        $cart = makeCart();
        $cart->forceFill(['user_id' => $student->id])->save();

        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000]);
        app(CartManager::class)->add($cart, productForCourse($course));

        $order = app(PlaceOrder::class)->handle($cart->refresh(), $student);

        expect(Enrollment::count())->toBe(0);

        app(RecordOrderPayment::class)->handle($order, $order->total(), 'bank_transfer', 'TRX-1');

        expect(Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->exists())->toBeTrue()
            ->and(Order::find($order->id)->status)->toBe('completed');
    });
});

it('keeps a partly paid order out of the student hands', function () {
    provision()->run(function (): void {
        $student = seedStudent();
        $cart = makeCart();
        $cart->forceFill(['user_id' => $student->id])->save();

        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 100000]);
        app(CartManager::class)->add($cart, productForCourse($course));
        $order = app(PlaceOrder::class)->handle($cart->refresh(), $student);

        app(RecordOrderPayment::class)->handle($order, Money::fromMinor(40000, 'EGP'), 'bank_transfer');

        expect(Order::find($order->id)->status)->toBe('awaiting_payment')
            ->and(Enrollment::count())->toBe(0);
    });
});

it('leaves a shippable order in processing, not completed', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        $cart->forceFill(['user_id' => seedStudent()->id])->save();

        app(CartManager::class)->add($cart, seedProduct([
            'type' => 'physical', 'requires_shipping' => true, 'price_minor' => 30000,
        ]));

        $order = app(PlaceOrder::class)->handle($cart->refresh(), null, ['email' => 'g@example.test']);
        app(RecordOrderPayment::class)->handle($order, $order->total(), 'cash_on_delivery');

        expect(Order::find($order->id)->status)->toBe('processing');
    });
});

it('never double-charges a course when the same payment arrives twice', function () {
    provision()->run(function (): void {
        $student = seedStudent();
        $cart = makeCart();
        $cart->forceFill(['user_id' => $student->id])->save();

        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000]);
        app(CartManager::class)->add($cart, productForCourse($course));
        $order = app(PlaceOrder::class)->handle($cart->refresh(), $student);

        app(RecordOrderPayment::class)->handle($order, $order->total(), 'paymob', 'REF-1');
        app(RecordOrderPayment::class)->handle($order->refresh(), $order->total(), 'paymob', 'REF-1');

        expect(Enrollment::where('user_id', $student->id)->count())->toBe(1);
    });
});

it('lets a free order through without any gateway', function () {
    provision()->run(function (): void {
        $cart = makeCart();
        app(CartManager::class)->add($cart, seedProduct(['price_minor' => 0]));

        $order = app(PlaceOrder::class)->handle($cart->refresh(), null, ['email' => 'g@example.test']);

        expect($order->status)->toBe('paid')
            ->and($order->total()->isZero())->toBeTrue();
    });
});

// ------------------------------------------------------------------
// الاسترداد
// ------------------------------------------------------------------

function paidCourseOrder(): array
{
    $student = seedStudent();
    $cart = makeCart();
    $cart->forceFill(['user_id' => $student->id])->save();

    $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000]);
    app(CartManager::class)->add($cart, productForCourse($course));

    $order = app(PlaceOrder::class)->handle($cart->refresh(), $student);
    app(RecordOrderPayment::class)->handle($order, $order->total(), 'bank_transfer', 'TRX');

    return [$order->refresh(), $student, $course];
}

it('refunds inside the window and pulls the access back', function () {
    provision()->run(function (): void {
        [$order, $student, $course] = paidCourseOrder();

        $refund = app(RefundOrder::class)->request($order, $student, 'غيّرت رأيي');
        app(RefundOrder::class)->approve($refund);

        expect(Order::find($order->id)->status)->toBe('refunded')
            ->and(Enrollment::where('user_id', $student->id)->first()->status)->toBe('refunded');
    });
});

it('keeps the student record after a refund so a repurchase finds it', function () {
    provision()->run(function (): void {
        [$order, $student, $course] = paidCourseOrder();

        $enrollment = Enrollment::firstOrFail();
        $enrollment->forceFill(['progress_percent' => 15])->save();

        app(RefundOrder::class)->approve(app(RefundOrder::class)->request($order, $student));

        expect(Enrollment::count())->toBe(1)
            ->and(Enrollment::first()->progress_percent)->toBe(15);
    });
});

it('refuses a refund once the window has passed', function () {
    provision()->run(function (): void {
        setting()->set('commerce.refund_days', 14);

        [$order, $student] = paidCourseOrder();
        $order->forceFill(['paid_at' => now()->subMonths(2)])->save();

        expect(fn () => app(RefundOrder::class)->request($order->refresh(), $student))
            ->toThrow(RuntimeException::class);
    });
});

it('refuses a refund to someone who already watched most of it', function () {
    provision()->run(function (): void {
        setting()->set('commerce.refund_max_progress', 20);

        [$order, $student] = paidCourseOrder();
        Enrollment::query()->update(['progress_percent' => 80]);

        expect(fn () => app(RefundOrder::class)->request($order, $student))
            ->toThrow(RuntimeException::class);
    });
});

// ------------------------------------------------------------------
// أكواد الشحن والمحفظة
// ------------------------------------------------------------------

it('generates a batch of unique, human-readable codes', function () {
    provision()->run(function (): void {
        $batch = app(GenerateCodes::class)->handle([
            'name' => 'كروت سبتمبر', 'quantity' => 50, 'type' => 'wallet', 'value_minor' => 10000,
        ]);

        $codes = RechargeCode::where('batch_id', $batch->id)->pluck('code');

        expect($codes)->toHaveCount(50)
            ->and($codes->unique())->toHaveCount(50)
            ->and($codes->first())->toMatch('/^[A-Z2-9]{4}(-[A-Z2-9]{4}){3}$/');
    });
});

it('tops up the wallet and writes the running balance', function () {
    provision()->run(function (): void {
        $student = seedStudent();
        $batch = app(GenerateCodes::class)->handle([
            'name' => 'كروت', 'quantity' => 2, 'type' => 'wallet', 'value_minor' => 15000,
        ]);

        $codes = RechargeCode::where('batch_id', $batch->id)->pluck('code');

        app(RedeemCode::class)->handle($student, $codes[0]);
        app(RedeemCode::class)->handle($student, $codes[1]);

        expect(WalletTransaction::balanceFor((int) $student->id, 'EGP')->minor)->toBe(30000);
    });
});

it('burns a code so it never works twice', function () {
    provision()->run(function (): void {
        $student = seedStudent();
        $batch = app(GenerateCodes::class)->handle([
            'name' => 'كروت', 'quantity' => 1, 'type' => 'wallet', 'value_minor' => 5000,
        ]);
        $code = RechargeCode::where('batch_id', $batch->id)->value('code');

        app(RedeemCode::class)->handle($student, $code);

        expect(fn () => app(RedeemCode::class)->handle($student, $code))
            ->toThrow(RuntimeException::class, 'استُخدم هذا الكود من قبل.');
    });
});

it('reads a code the student typed in lowercase with spaces', function () {
    provision()->run(function (): void {
        $student = seedStudent();
        $batch = app(GenerateCodes::class)->handle([
            'name' => 'كروت', 'quantity' => 1, 'type' => 'wallet', 'value_minor' => 5000,
        ]);
        $code = RechargeCode::where('batch_id', $batch->id)->value('code');

        expect(app(RedeemCode::class)->handle($student, ' '.mb_strtolower($code).' ')->status)->toBe('used');
    });
});

it('refuses an expired code and says so', function () {
    provision()->run(function (): void {
        $student = seedStudent();
        $code = RechargeCode::create([
            'code' => 'AAAA-BBBB-CCCC-DDDD',
            'type' => 'wallet', 'currency' => 'EGP', 'value_minor' => 5000,
            'status' => 'unused', 'expires_at' => now()->subDay(),
        ]);

        expect(fn () => app(RedeemCode::class)->handle($student, $code->code))
            ->toThrow(RuntimeException::class, 'انتهت صلاحية هذا الكود.');
    });
});

it('unlocks a course with a course code', function () {
    provision()->run(function (): void {
        $student = seedStudent();
        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000]);

        $batch = app(GenerateCodes::class)->handle([
            'name' => 'كروت الكورس', 'quantity' => 1, 'type' => 'course', 'course_id' => $course->id,
        ]);
        $code = RechargeCode::where('batch_id', $batch->id)->value('code');

        app(RedeemCode::class)->handle($student, $code);

        expect(Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->first()->source)
            ->toBe('code');
    });
});

it('pays from the wallet and refuses when the balance is short', function () {
    provision()->run(function (): void {
        setting()->set('payments.wallet_enabled', true);

        $student = seedStudent();
        $cart = makeCart();
        $cart->forceFill(['user_id' => $student->id])->save();

        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 50000]);
        app(CartManager::class)->add($cart, productForCourse($course));
        $order = app(PlaceOrder::class)->handle($cart->refresh(), $student);

        $wallet = app(WalletGateway::class);

        expect(fn () => $wallet->start($order))->toThrow(RuntimeException::class);

        WalletTransaction::create([
            'user_id' => $student->id, 'type' => 'credit', 'currency' => 'EGP',
            'amount_minor' => 60000, 'balance_after_minor' => 60000, 'source' => 'admin',
        ]);

        $wallet->start($order->refresh());

        expect(Order::find($order->id)->status)->toBe('completed')
            ->and(WalletTransaction::balanceFor((int) $student->id, 'EGP')->minor)->toBe(10000);
    });
});

// ------------------------------------------------------------------
// عمولات المدرّسين
// ------------------------------------------------------------------

it('books the instructor commission the moment the sale lands', function () {
    provision()->run(function (): void {
        $owner = User::where('role', 'owner')->firstOrFail();
        $instructor = Instructor::create([
            'user_id' => $owner->id, 'commission_rate' => 70, 'approved_at' => now(),
        ]);

        $student = seedStudent();
        $cart = makeCart();
        $cart->forceFill(['user_id' => $student->id])->save();

        $course = seedCourse([
            'enrollment_type' => 'paid', 'price_minor' => 100000, 'instructor_id' => $instructor->id,
        ]);
        app(CartManager::class)->add($cart, productForCourse($course));

        $order = app(PlaceOrder::class)->handle($cart->refresh(), $student);
        app(RecordOrderPayment::class)->handle($order, $order->total(), 'bank_transfer', 'T1');

        $earning = InstructorEarning::firstOrFail();

        expect($earning->amount_minor)->toBe(70000)
            ->and($earning->status)->toBe('available');
    });
});

it('holds the commission until the refund window closes', function () {
    provision()->run(function (): void {
        setting()->set('commerce.refund_days', 14);

        $owner = User::where('role', 'owner')->firstOrFail();
        $instructor = Instructor::create([
            'user_id' => $owner->id, 'commission_rate' => 50, 'approved_at' => now(),
        ]);

        $student = seedStudent();
        $cart = makeCart();
        $cart->forceFill(['user_id' => $student->id])->save();
        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 100000, 'instructor_id' => $instructor->id]);
        app(CartManager::class)->add($cart, productForCourse($course));
        $order = app(PlaceOrder::class)->handle($cart->refresh(), $student);
        app(RecordOrderPayment::class)->handle($order, $order->total(), 'cash', 'T2');

        // ناضجة بعد أسبوعين لا الآن
        expect(app(CreatePayout::class)->balanceFor($instructor)->minor)->toBe(0);

        InstructorEarning::query()->update(['available_at' => now()->subDay()]);

        expect(app(CreatePayout::class)->balanceFor($instructor->refresh())->minor)
            ->toBe(50000);
    });
});

it('reverses the commission when the order is refunded', function () {
    provision()->run(function (): void {
        $owner = User::where('role', 'owner')->firstOrFail();
        $instructor = Instructor::create([
            'user_id' => $owner->id, 'commission_rate' => 60, 'approved_at' => now(),
        ]);

        $student = seedStudent();
        $cart = makeCart();
        $cart->forceFill(['user_id' => $student->id])->save();
        $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 100000, 'instructor_id' => $instructor->id]);
        app(CartManager::class)->add($cart, productForCourse($course));
        $order = app(PlaceOrder::class)->handle($cart->refresh(), $student);
        app(RecordOrderPayment::class)->handle($order, $order->total(), 'cash', 'T3');

        app(RefundOrder::class)->approve(app(RefundOrder::class)->request($order->refresh(), $student));

        expect(InstructorEarning::first()->status)->toBe('reversed');
    });
});

it('gathers mature earnings into one payout and refuses an empty one', function () {
    provision()->run(function (): void {
        $owner = User::where('role', 'owner')->firstOrFail();
        $instructor = Instructor::create([
            'user_id' => $owner->id, 'commission_rate' => 70, 'approved_at' => now(),
        ]);

        expect(fn () => app(CreatePayout::class)->handle($instructor, 'bank'))
            ->toThrow(RuntimeException::class);

        foreach ([30000, 20000] as $amount) {
            InstructorEarning::create([
                'instructor_id' => $instructor->id, 'currency' => 'EGP',
                'amount_minor' => $amount, 'status' => 'available', 'available_at' => now()->subDay(),
            ]);
        }

        $payout = app(CreatePayout::class)->handle($instructor, 'instapay');

        expect($payout->amount_minor)->toBe(50000)
            ->and(InstructorEarning::where('status', 'paid')->count())->toBe(2);
    });
});

it('nets a reversal off the next payout instead of overpaying', function () {
    provision()->run(function (): void {
        $owner = User::where('role', 'owner')->firstOrFail();
        $instructor = Instructor::create([
            'user_id' => $owner->id, 'commission_rate' => 70, 'approved_at' => now(),
        ]);

        InstructorEarning::create([
            'instructor_id' => $instructor->id, 'currency' => 'EGP',
            'amount_minor' => 50000, 'status' => 'available', 'available_at' => now()->subDay(),
        ]);

        InstructorEarning::create([
            'instructor_id' => $instructor->id, 'currency' => 'EGP',
            'amount_minor' => -20000, 'status' => 'available', 'available_at' => now()->subDay(),
        ]);

        expect(app(CreatePayout::class)->handle($instructor, 'bank')->amount_minor)
            ->toBe(30000);
    });
});
