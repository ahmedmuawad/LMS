<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Gateways\Drivers;

use App\Core\Support\Money;
use App\Modules\Commerce\Gateways\PaymentIntent;
use App\Modules\Commerce\Gateways\PaymentResult;
use App\Modules\Commerce\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * فوري — كود يُدفع في أي منفذ.
 *
 * يخدم من لا يملك بطاقة، وهم أكثر من يُفترض. الطلب يبقى معلّقاً
 * حتى يصل إشعار السداد، وللكود مهلة تُعرض للعميل صراحةً.
 */
final class FawryGateway extends BaseGateway
{
    public function key(): string
    {
        return 'fawry';
    }

    protected function defaultTitle(): string
    {
        return __('كود فوري');
    }

    public function isReady(): bool
    {
        return parent::isReady()
            && filled($this->setting('merchant_code'))
            && filled($this->setting('security_key'));
    }

    private function base(): string
    {
        return $this->isTestMode()
            ? 'https://atfawry.fawrystaging.com'
            : 'https://www.atfawry.com';
    }

    public function start(Order $order): PaymentIntent
    {
        $merchant = (string) $this->setting('merchant_code');
        $secret = (string) $this->setting('security_key');
        $email = $order->customerEmail() ?? 'na@example.com';
        $amount = number_format($order->total_minor / 100, 2, '.', '');

        $signature = hash('sha256', $merchant.$order->number.$email.'PAYATFAWRY'.$amount.$secret);

        $response = Http::asJson()->post($this->base().'/ECommerceWeb/Fawry/payments/charge', [
            'merchantCode' => $merchant,
            'merchantRefNum' => $order->number,
            'customerMobile' => $order->billing['phone'] ?? '',
            'customerEmail' => $email,
            'paymentMethod' => 'PAYATFAWRY',
            'amount' => (float) $amount,
            'currencyCode' => $order->currency,
            'description' => __('طلب رقم :n', ['n' => $order->number]),
            'paymentExpiry' => now()->addDays(3)->getTimestampMs(),
            'chargeItems' => $order->items->map(fn ($item): array => [
                'itemId' => (string) $item->getKey(),
                'description' => $item->title(),
                'price' => (float) number_format($item->unit_price_minor / 100, 2, '.', ''),
                'quantity' => $item->quantity,
            ])->all(),
            'signature' => $signature,
        ]);

        $code = $response->json('referenceNumber');

        if (! $response->successful() || blank($code)) {
            throw new RuntimeException(__('تعذّر إصدار كود فوري.'));
        }

        return PaymentIntent::instructions(
            __('كود الدفع: :code — ادفعه في أي منفذ فوري خلال ٣ أيام.', ['code' => $code]),
            reference: (string) $code,
            meta: ['code' => $code, 'expires_at' => now()->addDays(3)->toIso8601String()],
        );
    }

    public function handleCallback(Request $request): ?PaymentResult
    {
        $payload = $request->all();
        $secret = (string) $this->setting('security_key');

        $expected = hash('sha256',
            ($payload['fawryRefNumber'] ?? '')
            .($payload['merchantRefNumber'] ?? '')
            .number_format((float) ($payload['paymentAmount'] ?? 0), 2, '.', '')
            .number_format((float) ($payload['orderAmount'] ?? 0), 2, '.', '')
            .($payload['orderStatus'] ?? '')
            .($payload['paymentMethod'] ?? '')
            .($payload['paymentRefrenceNumber'] ?? '')
            .$secret,
        );

        if (! hash_equals($expected, (string) ($payload['messageSignature'] ?? ''))) {
            return null;
        }

        return new PaymentResult(
            orderNumber: (string) ($payload['merchantRefNumber'] ?? ''),
            successful: ($payload['orderStatus'] ?? '') === 'PAID',
            amount: Money::fromDecimal((string) ($payload['paymentAmount'] ?? 0), 'EGP'),
            reference: (string) ($payload['fawryRefNumber'] ?? ''),
            raw: $payload,
        );
    }
}
