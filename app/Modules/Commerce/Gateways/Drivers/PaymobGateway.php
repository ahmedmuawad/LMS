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

/**
 * Paymob — الأوسع انتشاراً في مصر: بطاقات ومحافظ وأقساط وكاش.
 *
 * التدفّق ثلاث خطوات: رمز مصادقة، ثم تسجيل الطلب، ثم مفتاح دفع
 * يُفتح به إطار الدفع. ونتحقق من HMAC في الردّ — بدونه يستطيع أي
 * أحد أن يُخبرنا أن طلباً دُفع.
 */
final class PaymobGateway extends BaseGateway
{
    private const BASE = 'https://accept.paymob.com/api';

    public function key(): string
    {
        return 'paymob';
    }

    protected function defaultTitle(): string
    {
        return __('بطاقة أو محفظة إلكترونية');
    }

    public function isReady(): bool
    {
        return parent::isReady()
            && filled($this->setting('api_key'))
            && filled($this->setting('integration_card'));
    }

    public function start(Order $order): PaymentIntent
    {
        $token = $this->authenticate();
        $paymobOrder = $this->registerOrder($token, $order);
        $paymentKey = $this->paymentKey($token, $order, $paymobOrder['id']);

        return PaymentIntent::redirect(
            'https://accept.paymob.com/api/acceptance/iframes/'.$this->setting('iframe_id').'?payment_token='.$paymentKey,
            reference: (string) $paymobOrder['id'],
        );
    }

    public function handleCallback(Request $request): ?PaymentResult
    {
        $payload = $request->all();

        if (! $this->hmacMatches($request)) {
            Log::warning('Paymob callback with a bad HMAC', ['ip' => $request->ip()]);

            return null;
        }

        $data = $payload['obj'] ?? $payload;
        $orderNumber = $data['order']['merchant_order_id'] ?? null;

        if ($orderNumber === null) {
            return null;
        }

        return new PaymentResult(
            orderNumber: (string) $orderNumber,
            successful: (bool) ($data['success'] ?? false) && ! ($data['is_voided'] ?? false),
            amount: Money::fromMinor((int) ($data['amount_cents'] ?? 0), (string) ($data['currency'] ?? 'EGP')),
            reference: (string) ($data['id'] ?? ''),
            failureReason: $data['data']['message'] ?? null,
            raw: $data,
        );
    }

    /**
     * توقيع Paymob: حقول بترتيب ثابت تُوصَل ثم تُجزَّأ بـ HMAC-SHA512.
     * أي اختلاف في الترتيب يُفشل التحقق، فالترتيب هنا جزء من العقد.
     */
    private function hmacMatches(Request $request): bool
    {
        $secret = (string) $this->setting('hmac_secret');
        $received = (string) $request->query('hmac', $request->input('hmac', ''));

        if (blank($secret) || blank($received)) {
            return false;
        }

        $data = $request->input('obj', $request->all());

        $fields = [
            'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction',
            'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
            'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
            'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
        ];

        $concatenated = '';

        foreach ($fields as $field) {
            $value = data_get($data, $field);
            $concatenated .= is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return hash_equals(hash_hmac('sha512', $concatenated, $secret), $received);
    }

    private function authenticate(): string
    {
        $response = Http::asJson()->post(self::BASE.'/auth/tokens', [
            'api_key' => $this->setting('api_key'),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(__('تعذّر الاتصال ببوابة الدفع.'));
        }

        return (string) $response->json('token');
    }

    private function registerOrder(string $token, Order $order): array
    {
        $response = Http::asJson()->post(self::BASE.'/ecommerce/orders', [
            'auth_token' => $token,
            'delivery_needed' => false,
            'merchant_order_id' => $order->number,
            'amount_cents' => (string) $order->total_minor,
            'currency' => $order->currency,
            'items' => $order->items->map(fn ($item): array => [
                'name' => $item->title(),
                'amount_cents' => (string) $item->total_minor,
                'quantity' => (string) $item->quantity,
            ])->all(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(__('تعذّر تسجيل الطلب لدى بوابة الدفع.'));
        }

        return (array) $response->json();
    }

    private function paymentKey(string $token, Order $order, int $paymobOrderId): string
    {
        $billing = $order->billing ?? [];

        $response = Http::asJson()->post(self::BASE.'/acceptance/payment_keys', [
            'auth_token' => $token,
            'amount_cents' => (string) $order->total_minor,
            'expiration' => 3600,
            'order_id' => $paymobOrderId,
            'currency' => $order->currency,
            'integration_id' => (int) $this->setting('integration_card'),
            'billing_data' => [
                'email' => $order->customerEmail() ?? 'na@example.com',
                'first_name' => $billing['first_name'] ?? $order->customerName(),
                'last_name' => $billing['last_name'] ?? 'NA',
                'phone_number' => $billing['phone'] ?? 'NA',
                'apartment' => 'NA', 'floor' => 'NA', 'street' => 'NA', 'building' => 'NA',
                'shipping_method' => 'NA', 'postal_code' => 'NA', 'city' => 'NA',
                'country' => $billing['country'] ?? 'EG', 'state' => 'NA',
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(__('تعذّر بدء الدفع.'));
        }

        return (string) $response->json('token');
    }
}
