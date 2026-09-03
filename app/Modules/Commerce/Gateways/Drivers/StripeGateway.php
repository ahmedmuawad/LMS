<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Gateways\Drivers;

use App\Core\Support\Money;
use App\Modules\Commerce\Gateways\PaymentIntent;
use App\Modules\Commerce\Gateways\PaymentResult;
use App\Modules\Commerce\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Stripe — البطاقات الدولية عبر Checkout المستضاف. */
final class StripeGateway extends BaseGateway
{
    private const BASE = 'https://api.stripe.com/v1';

    public function key(): string
    {
        return 'stripe';
    }

    protected function defaultTitle(): string
    {
        return __('بطاقة ائتمان دولية');
    }

    public function isReady(): bool
    {
        return parent::isReady() && filled($this->setting('secret_key'));
    }

    public function start(Order $order): PaymentIntent
    {
        $response = Http::withToken((string) $this->setting('secret_key'))
            ->asForm()
            ->post(self::BASE.'/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => $order->number,
                'success_url' => url('/checkout/return/stripe?order='.$order->number),
                'cancel_url' => url('/checkout/cancel?order='.$order->number),
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => mb_strtolower($order->currency),
                'line_items[0][price_data][unit_amount]' => (int) $order->total_minor,
                'line_items[0][price_data][product_data][name]' => __('طلب رقم :n', ['n' => $order->number]),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(__('تعذّر بدء الدفع.'));
        }

        return PaymentIntent::redirect((string) $response->json('url'), (string) $response->json('id'));
    }

    public function handleCallback(Request $request): ?PaymentResult
    {
        if (! $this->signatureMatches($request)) {
            Log::warning('Stripe callback with a bad signature', ['ip' => $request->ip()]);

            return null;
        }

        $event = $request->all();
        $session = $event['data']['object'] ?? [];

        if (($event['type'] ?? '') !== 'checkout.session.completed') {
            return null;
        }

        return new PaymentResult(
            orderNumber: (string) ($session['client_reference_id'] ?? ''),
            successful: ($session['payment_status'] ?? '') === 'paid',
            amount: Money::fromMinor((int) ($session['amount_total'] ?? 0), mb_strtoupper((string) ($session['currency'] ?? 'usd'))),
            reference: (string) ($session['payment_intent'] ?? $session['id'] ?? ''),
            raw: $session,
        );
    }

    /**
     * توقيع Stripe: طابع زمني ونصّ الجسم معاً.
     * نرفض التوقيع القديم كي لا يُعاد إرسال ردّ ناجح لاحقاً.
     */
    private function signatureMatches(Request $request): bool
    {
        $secret = (string) $this->setting('webhook_secret');
        $header = (string) $request->header('Stripe-Signature', '');

        if (blank($secret) || blank($header)) {
            return false;
        }

        $parts = [];

        foreach (explode(',', $header) as $piece) {
            [$k, $v] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$k][] = $v;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);

        if ($timestamp === 0 || abs(time() - $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($parts['v1'] ?? [] as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
