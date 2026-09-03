<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Core\Support\Money;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * السلة.
 *
 * تُفتح للزائر برمز في الكوكي، وتُنقل إليه عند تسجيل الدخول:
 * سلة تضيع عند التسجيل هي أسرع طريق لفقد البيع.
 */
final class CartManager
{
    public const COOKIE = 'cart_token';

    public function current(Request $request, bool $create = true): ?Cart
    {
        $user = $request->user();
        $token = $request->cookie(self::COOKIE);

        $cart = $user !== null
            ? Cart::with('items.product')->where('user_id', $user->getKey())->latest()->first()
            : null;

        if ($cart === null && filled($token)) {
            $cart = Cart::with('items.product')->where('token', $token)->whereNull('user_id')->first();

            // زائر سجّل دخوله: سلته تنتقل معه بدل أن تُهجر
            if ($cart !== null && $user !== null) {
                $cart->forceFill(['user_id' => $user->getKey()])->save();
            }
        }

        if ($cart === null && $create) {
            $cart = Cart::create([
                'user_id' => $user?->getKey(),
                'token' => (string) Str::uuid(),
                'currency' => (string) (tenant('currency') ?? 'EGP'),
                'expires_at' => now()->addDays((int) setting('commerce.cart_lifetime_days', 30)),
            ]);
        }

        return $cart;
    }

    public function add(Cart $cart, Product $product, int $quantity = 1, ?ProductVariant $variant = null): CartItem
    {
        if (! $product->canSell($quantity)) {
            throw new RuntimeException(__('هذا المنتج غير متاح للشراء الآن.'));
        }

        if ($product->currency !== $cart->currency) {
            throw new RuntimeException(__('لا يمكن جمع عملتين مختلفتين في سلة واحدة.'));
        }

        $price = $variant?->price() ?? $product->price();

        $item = CartItem::firstOrNew([
            'cart_id' => $cart->getKey(),
            'product_id' => $product->getKey(),
            'variant_id' => $variant?->getKey(),
        ]);

        /*
         | الكورس يُشترى مرة واحدة: زيادة كميته تعني دفع ثمنه مرتين
         | مقابل وصول واحد — وهذا ما يعود العميل يطالب باسترداده.
         */
        $repeatable = in_array($product->type, ['physical', 'digital'], true);

        $item->quantity = $repeatable ? (int) $item->quantity + $quantity : 1;
        $item->unit_price_minor = $price->minor;
        $item->save();

        return $item;
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $item->delete();

            return;
        }

        if (! $item->product?->canSell($quantity)) {
            throw new RuntimeException(__('الكمية المطلوبة غير متوفّرة.'));
        }

        $item->forceFill(['quantity' => $quantity])->save();
    }

    /** @return array{subtotal: Money, discount: Money, tax: Money, shipping: Money, total: Money} */
    public function totals(Cart $cart): array
    {
        $cart->loadMissing(['items.product', 'coupon']);

        $subtotal = $cart->subtotal();
        $discount = $cart->coupon?->discountOn($subtotal) ?? Money::zero($cart->currency);
        $net = $subtotal->minus($discount);

        $shipping = $this->shippingFor($cart, $net);
        $tax = $this->taxOn($net->plus($shipping));

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => $net->plus($shipping)->plus($tax),
        ];
    }

    private function shippingFor(Cart $cart, Money $net): Money
    {
        if (! setting('commerce.shipping_enabled', false) || ! $cart->hasShippable()) {
            return Money::zero($cart->currency);
        }

        $freeOver = (int) setting('commerce.free_shipping_over', 0);

        if ($freeOver > 0 && $net->minor >= $freeOver * 100) {
            return Money::zero($cart->currency);
        }

        return Money::fromMinor((int) setting('commerce.flat_shipping_minor', 0), $cart->currency);
    }

    /**
     * الضريبة تُحسب على الصافي بعد الخصم.
     * والسعر «الشامل» لا تُضاف إليه ضريبة فوقه — وإلا حُسبت مرتين.
     */
    private function taxOn(Money $amount): Money
    {
        if (! setting('currency.tax_enabled', false)) {
            return Money::zero($amount->currency);
        }

        if (setting('currency.prices_include_tax', 'inclusive') === 'inclusive') {
            return Money::zero($amount->currency);
        }

        return $amount->percentage((float) setting('currency.default_rate', 0));
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->forceFill(['coupon_id' => null])->save();
    }
}
