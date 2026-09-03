<?php

declare(strict_types=1);

namespace App\Http\Controllers\Commerce;

use App\Modules\Commerce\Actions\CartManager;
use App\Modules\Commerce\Actions\PlaceOrder;
use App\Modules\Commerce\Actions\RecordOrderPayment;
use App\Modules\Commerce\Gateways\GatewayManager;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\WalletTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class CheckoutController
{
    public function __construct(
        private readonly CartManager $carts,
        private readonly GatewayManager $gateways,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $cart = $this->carts->current($request, create: false);

        if ($cart === null || $cart->loadMissing('items')->isEmpty()) {
            return redirect(url('/cart'));
        }

        $totals = $this->carts->totals($cart);

        return view('commerce.checkout', [
            'cart' => $cart,
            'totals' => $totals,
            'gateways' => $this->gateways->available($totals['total'], (string) tenant('country')),
            'balance' => $request->user() === null
                ? null
                : WalletTransaction::balanceFor((int) $request->user()->getKey(), $cart->currency),
        ]);
    }

    public function place(Request $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $cart = $this->carts->current($request, create: false);

        if ($cart === null || $cart->loadMissing('items')->isEmpty()) {
            return redirect(url('/cart'))->withErrors(['cart' => __('سلتك فارغة.')]);
        }

        $guestAllowed = (bool) setting('commerce.guest_checkout', false);

        $input = $request->validate([
            'gateway' => ['required', 'string', 'max:32'],
            'email' => [$request->user() === null && $guestAllowed ? 'required' : 'nullable', 'email'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->user() === null && ! $guestAllowed) {
            return redirect(url('/login'))->with('status', __('سجّل دخولك لإتمام الشراء.'));
        }

        if (! $this->gateways->has($input['gateway'])) {
            return back()->withErrors(['gateway' => __('وسيلة دفع غير معروفة.')]);
        }

        $gateway = $this->gateways->resolve($input['gateway']);

        if (! $gateway->isReady()) {
            return back()->withErrors(['gateway' => __('وسيلة الدفع هذه غير متاحة الآن.')]);
        }

        try {
            $order = $placeOrder->handle($cart, $request->user(), [
                'email' => $input['email'] ?? null,
                'billing' => array_filter([
                    'name' => $input['name'] ?? $request->user()?->name,
                    'email' => $input['email'] ?? $request->user()?->email,
                    'phone' => $input['phone'] ?? null,
                    'country' => tenant('country'),
                ]),
                'notes' => $input['notes'] ?? null,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        $order->forceFill(['gateway' => $gateway->key()])->save();

        // الطلب المجاني لا يمرّ ببوابة أصلاً
        if ($order->total()->isZero()) {
            app(RecordOrderPayment::class)->handle($order, $order->total(), 'free', $order->number);

            return redirect(url('/orders/'.$order->number))->with('status', __('تم! المحتوى متاح لك الآن.'));
        }

        try {
            $intent = $gateway->start($order->load('items'));
        } catch (Throwable $e) {
            app(RecordOrderPayment::class)->fail($order, $gateway->key(), $e->getMessage());

            return redirect(url('/orders/'.$order->number))->withErrors(['gateway' => $e->getMessage()]);
        }

        if ($intent->mode === 'redirect' && $intent->url !== null) {
            return redirect()->away($intent->url);
        }

        if ($intent->mode === 'instructions') {
            $order->forceFill(['notes' => trim(($order->notes ?? '')."\n".$intent->message)])->save();
        }

        return redirect(url('/orders/'.$order->number))
            ->with('status', $intent->message ?? __('استُلم طلبك.'));
    }

    public function order(Request $request, string $number): View
    {
        $order = Order::with(['items', 'payments'])->where('number', $number)->firstOrFail();

        // الطلب لصاحبه وحده، أو لمن يملك اللوحة
        abort_unless(
            ($order->user_id !== null && $order->user_id === $request->user()?->getKey())
            || $request->user()?->canAccessPanel() === true
            || ($order->user_id === null && $request->session()->get('guest_order') === $order->number),
            403,
        );

        return view('commerce.order', ['order' => $order]);
    }
}
