<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Commerce\Actions\CartManager;
use App\Modules\Commerce\Actions\PlaceOrder;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Growth\Models\Affiliate;
use App\Modules\Growth\Models\Campaign;
use App\Modules\Growth\Models\CampaignStep;
use App\Modules\Lms\Models\Course;
use Illuminate\Support\Str;

/*
 | مصانع بيانات التجارة — تُستدعى دائماً داخل سياق مشترك.
 */

function seedProduct(array $overrides = []): Product
{
    return Product::create([
        'type' => 'digital',
        'slug' => 'product-'.uniqid(),
        'title' => ['ar' => 'منتج رقمي', 'en' => 'Digital product'],
        'currency' => (string) (tenant('currency') ?? 'EGP'),
        'price_minor' => 20000,
        'status' => 'published',
        ...$overrides,
    ]);
}

/** منتج الكورس يُنشئه المراقب تلقائياً عند النشر. */
function productForCourse(Course $course): Product
{
    return Product::where('purchasable_type', Course::class)
        ->where('purchasable_id', $course->getKey())
        ->firstOrFail();
}

function seedCoupon(array $overrides = []): Coupon
{
    return Coupon::create([
        'code' => 'SAVE'.random_int(100, 999),
        'type' => 'percent',
        'value' => 20,
        'usage_limit_per_user' => 1,
        'is_active' => true,
        ...$overrides,
    ]);
}

/*
 | مصانع النمو — تُستدعى دائماً داخل سياق مشترك.
 */

function seedAffiliate(string $code = 'promo1', array $overrides = []): Affiliate
{
    return Affiliate::create([
        'user_id' => ($overrides['user_id'] ?? null) ?: seedStudent('affiliate-'.$code.'@t.test')->getKey(),
        'code' => $code,
        'status' => 'active',
        'approved_at' => now(),
        ...$overrides,
    ]);
}

/** سلة فيها كورس مدفوع باسم مستخدم بعينه. */
function seedCartFor(User $user, Course $course): Cart
{
    $cart = Cart::create([
        'token' => Str::random(40),
        'user_id' => $user->getKey(),
        'currency' => (string) (tenant('currency') ?? 'EGP'),
    ]);

    app(CartManager::class)->add($cart, productForCourse($course));

    return $cart->refresh();
}

/** طلب مدفوع جاهز للنسب إلى مسوّق. */
function seedPaidOrder(array $overrides = []): Order
{
    $user = ($overrides['user_id'] ?? null) === null
        ? seedStudent('buyer-'.uniqid().'@t.test')
        : User::findOrFail($overrides['user_id']);

    $course = seedCourse(['enrollment_type' => 'paid', 'price_minor' => 120000]);
    $cart = seedCartFor($user, $course);

    $order = app(PlaceOrder::class)->handle($cart, $user);

    $order->forceFill([
        'status' => 'paid',
        'paid_at' => now(),
        ...array_diff_key($overrides, ['user_id' => null]),
    ])->save();

    return $order->refresh();
}

function seedCampaign(array $overrides = []): Campaign
{
    $campaign = Campaign::create([
        'key' => 'campaign-'.uniqid(),
        'name' => ['ar' => 'حملة اختبار'],
        'trigger' => 'manual',
        'status' => 'active',
        ...$overrides,
    ]);

    foreach ([60, 1440, 4320] as $position => $minutes) {
        CampaignStep::create([
            'campaign_id' => $campaign->getKey(),
            'position' => $position,
            'delay_minutes' => $minutes,
            'event' => 'commerce.abandoned_cart',
            'is_active' => true,
        ]);
    }

    return $campaign->load('steps');
}
