<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\Order;
use RuntimeException;

/**
 * الكوبون.
 *
 * كل رفض يُشرح سببه: «انتهت صلاحيته» أفهم من «كود غير صالح»،
 * والغموض هنا يُقرأ عطلاً في الموقع لا شرطاً في العرض.
 */
final class ApplyCoupon
{
    public function handle(Cart $cart, string $code): Coupon
    {
        $coupon = Coupon::whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])->first();

        if ($coupon === null || ! $coupon->is_active) {
            throw new RuntimeException(__('هذا الكود غير موجود.'));
        }

        if (! $coupon->hasStarted()) {
            throw new RuntimeException(__('لم يبدأ سريان هذا الكود بعد.'));
        }

        if ($coupon->hasExpired()) {
            throw new RuntimeException(__('انتهت صلاحية هذا الكود.'));
        }

        if ($coupon->isExhausted()) {
            throw new RuntimeException(__('استُنفد هذا الكود.'));
        }

        $subtotal = $cart->subtotal();

        if ($subtotal->minor < (int) $coupon->min_order_minor) {
            throw new RuntimeException(__('هذا الكود يحتاج حداً أدنى للطلب.'));
        }

        $this->assertUserMayUse($coupon, $cart);

        $cart->forceFill(['coupon_id' => $coupon->getKey()])->save();

        return $coupon;
    }

    private function assertUserMayUse(Coupon $coupon, Cart $cart): void
    {
        if ($cart->user_id === null) {
            return;
        }

        $used = $coupon->usages()->where('user_id', $cart->user_id)->count();

        if ($used >= (int) $coupon->usage_limit_per_user) {
            throw new RuntimeException(__('استخدمت هذا الكود من قبل.'));
        }

        if ($coupon->first_order_only && Order::where('user_id', $cart->user_id)->whereIn('status', Order::FULFILLED)->exists()) {
            throw new RuntimeException(__('هذا الكود لأول طلب فقط.'));
        }
    }

    public function remove(Cart $cart): void
    {
        $cart->forceFill(['coupon_id' => null])->save();
    }
}
