<?php

declare(strict_types=1);

namespace App\Http\Controllers\Commerce;

use App\Modules\Commerce\Actions\ApplyCoupon;
use App\Modules\Commerce\Actions\CartManager;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Lms\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

final class CartController
{
    public function __construct(private readonly CartManager $carts) {}

    public function show(Request $request): View
    {
        $cart = $this->carts->current($request);
        $cart->loadMissing(['items.product', 'items.variant', 'coupon']);

        return view('commerce.cart', [
            'cart' => $cart,
            'totals' => $this->carts->totals($cart),
        ]);
    }

    public function add(Request $request, ApplyCoupon $coupons): RedirectResponse
    {
        $input = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $product = $this->resolveProduct($input);

        if ($product === null) {
            return back()->withErrors(['cart' => __('هذا المنتج غير متاح للبيع.')]);
        }

        $cart = $this->carts->current($request);

        try {
            $this->carts->add(
                $cart,
                $product,
                (int) ($input['quantity'] ?? 1),
                isset($input['variant_id']) ? ProductVariant::find($input['variant_id']) : null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return redirect(url('/cart'))
            ->withCookie(cookie(CartManager::COOKIE, $cart->token, 60 * 24 * 30))
            ->with('status', __('أُضيف إلى سلتك.'));
    }

    public function update(Request $request, string $itemId): RedirectResponse
    {
        $cart = $this->carts->current($request, create: false);

        abort_if($cart === null, 404);

        $item = CartItem::where('cart_id', $cart->getKey())->findOrFail($itemId);

        $input = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);

        try {
            $this->carts->updateQuantity($item, (int) $input['quantity']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return back()->with('status', __('حُدِّثت السلة.'));
    }

    public function remove(Request $request, string $itemId): RedirectResponse
    {
        $cart = $this->carts->current($request, create: false);

        abort_if($cart === null, 404);

        CartItem::where('cart_id', $cart->getKey())->whereKey($itemId)->delete();

        return back()->with('status', __('أُزيل من سلتك.'));
    }

    public function applyCoupon(Request $request, ApplyCoupon $coupons): RedirectResponse
    {
        $cart = $this->carts->current($request);

        $input = $request->validate(['code' => ['required', 'string', 'max:48']]);

        try {
            $coupons->handle($cart, $input['code']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['coupon' => $e->getMessage()]);
        }

        return back()->with('status', __('طُبِّق الكوبون.'));
    }

    public function removeCoupon(Request $request, ApplyCoupon $coupons): RedirectResponse
    {
        $cart = $this->carts->current($request, create: false);

        if ($cart !== null) {
            $coupons->remove($cart);
        }

        return back()->with('status', __('أُزيل الكوبون.'));
    }

    /** @param  array<string, mixed>  $input */
    private function resolveProduct(array $input): ?Product
    {
        if (filled($input['product_id'] ?? null)) {
            return Product::published()->find($input['product_id']);
        }

        // الكورس يُشترى من صفحته مباشرة، ولا نطلب من الطالب معرفة رقم منتجه
        if (filled($input['course_id'] ?? null)) {
            return Product::published()
                ->where('purchasable_type', Course::class)
                ->where('purchasable_id', $input['course_id'])
                ->first();
        }

        return null;
    }
}
