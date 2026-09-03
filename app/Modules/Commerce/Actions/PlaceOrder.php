<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Models\User;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Models\CouponUsage;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Lms\Models\Course;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * تحويل السلة إلى طلب.
 *
 * البنود تُجمَّد بأسعارها وعناوينها: ما رآه العميل عند الشراء هو
 * ما تحمله فاتورته إلى الأبد، مهما تغيّر المنتج بعدها.
 */
final class PlaceOrder
{
    public function __construct(private readonly CartManager $carts) {}

    /** @param  array<string, mixed>  $details */
    public function handle(Cart $cart, ?User $user, array $details = []): Order
    {
        $cart->loadMissing(['items.product', 'items.variant', 'coupon']);

        if ($cart->isEmpty()) {
            throw new RuntimeException(__('السلة فارغة.'));
        }

        foreach ($cart->items as $item) {
            if (! $item->product?->canSell($item->quantity)) {
                throw new RuntimeException(__('«:product» لم يعد متاحاً.', [
                    'product' => $item->product?->title ?? '—',
                ]));
            }
        }

        $totals = $this->carts->totals($cart);

        return DB::transaction(function () use ($cart, $user, $details, $totals): Order {
            $order = Order::create([
                'number' => OrderNumber::next(),
                'user_id' => $user?->getKey(),
                'guest_email' => $user === null ? ($details['email'] ?? null) : null,
                'status' => $totals['total']->isZero() ? 'paid' : 'awaiting_payment',
                'currency' => $cart->currency,
                'subtotal_minor' => $totals['subtotal']->minor,
                'discount_minor' => $totals['discount']->minor,
                'tax_minor' => $totals['tax']->minor,
                'shipping_minor' => $totals['shipping']->minor,
                'total_minor' => $totals['total']->minor,
                'tax_rate' => (float) setting('currency.default_rate', 0),
                'coupon_id' => $cart->coupon_id,
                'coupon_code' => $cart->coupon?->code,
                'billing' => $details['billing'] ?? null,
                'shipping' => $details['shipping'] ?? null,
                'notes' => $details['notes'] ?? null,
                'placed_at' => now(),
                'paid_at' => $totals['total']->isZero() ? now() : null,
                'ip' => $details['ip'] ?? null,
                'user_agent' => mb_substr((string) ($details['user_agent'] ?? ''), 0, 512) ?: null,
            ]);

            foreach ($cart->items as $item) {
                $this->addItem($order, $item);
            }

            if ($cart->coupon !== null) {
                $cart->coupon->increment('used_count');

                CouponUsage::create([
                    'coupon_id' => $cart->coupon_id,
                    'order_id' => $order->getKey(),
                    'user_id' => $user?->getKey(),
                    'discount_minor' => $totals['discount']->minor,
                ]);
            }

            $this->carts->clear($cart);

            return $order;
        });
    }

    private function addItem(Order $order, CartItem $item): void
    {
        $product = $item->product;
        $line = $item->total();

        // العمولة تُحسب على البند لا على الطلب: كل مدرّس بنسبته
        $instructor = $product?->purchasable_type === Course::class
            ? Course::find($product->purchasable_id)?->instructor
            : null;

        $rate = (float) ($instructor?->commission_rate ?? 0);

        OrderItem::create([
            'order_id' => $order->getKey(),
            'product_id' => $product?->getKey(),
            'purchasable_type' => $product?->purchasable_type,
            'purchasable_id' => $product?->purchasable_id,
            'title_snapshot' => $product?->getTranslations('title') ?? ['ar' => '—'],
            'unit_price_minor' => $item->unit_price_minor,
            'quantity' => $item->quantity,
            'total_minor' => $line->minor,
            'instructor_id' => $instructor?->getKey(),
            'commission_minor' => $rate > 0 ? $line->percentage($rate)->minor : 0,
        ]);

        if ($product?->manage_stock) {
            $product->decrement('stock_qty', $item->quantity);
        }
    }
}
