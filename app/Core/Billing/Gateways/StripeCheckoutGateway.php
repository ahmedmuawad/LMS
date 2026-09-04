<?php

declare(strict_types=1);

namespace App\Core\Billing\Gateways;

use App\Core\Billing\Models\Invoice;
use App\Core\Support\Money;
use App\Modules\Commerce\Gateways\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Stripe Checkout لاشتراكاتنا.
 *
 * لا حزمة خارجية (ADR-002): طلبان اثنان إلى واجهة Stripe يكفيان —
 * إنشاء جلسة دفع، وقراءتها عند العودة للتأكّد من أنها دُفعت فعلاً.
 * التأكّد من الخادم لا من رابط العودة: من يعرف رابط النجاح يستطيع
 * فتحه بنفسه.
 */
final class StripeCheckoutGateway implements PlatformGateway
{
    private const API = 'https://api.stripe.com/v1';

    public function key(): string
    {
        return 'stripe';
    }

    public function title(): string
    {
        return __('بطاقة ائتمان');
    }

    public function description(): string
    {
        return __('فيزا أو ماستركارد — تُفعَّل منصّتك فور نجاح الدفع.');
    }

    public function isReady(): bool
    {
        return filled(config('platform-billing.stripe.secret'));
    }

    public function start(Invoice $invoice, string $returnUrl): PaymentIntent
    {
        $response = Http::asForm()
            ->withToken((string) config('platform-billing.stripe.secret'))
            ->post(self::API.'/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $returnUrl.'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $returnUrl.'?cancelled=1',
                'client_reference_id' => (string) $invoice->id,
                'customer_email' => $invoice->billing_details['email'] ?? null,
                'metadata' => ['invoice_id' => (string) $invoice->id, 'invoice_number' => $invoice->number],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($invoice->currency),
                        'unit_amount' => $invoice->total_minor,
                        'product_data' => ['name' => $this->lineTitle($invoice)],
                    ],
                ]],
            ]);

        if ($response->failed()) {
            Log::error('تعذّر إنشاء جلسة دفع Stripe', [
                'invoice' => $invoice->number,
                'error' => $response->json('error.message'),
            ]);

            throw new RuntimeException(__('تعذّر بدء الدفع الآن. جرّب مرة أخرى أو اختر التحويل البنكي.'));
        }

        return PaymentIntent::redirect((string) $response->json('url'), (string) $response->json('id'));
    }

    public function handleCallback(Request $request): ?PlatformPaymentResult
    {
        $sessionId = (string) $request->query('session_id', '');

        if ($sessionId === '') {
            return null;
        }

        $response = Http::withToken((string) config('platform-billing.stripe.secret'))
            ->get(self::API.'/checkout/sessions/'.$sessionId);

        if ($response->failed()) {
            return PlatformPaymentResult::failed(__('تعذّر التحقّق من الدفع.'));
        }

        $invoiceId = (int) ($response->json('metadata.invoice_id') ?? $response->json('client_reference_id') ?? 0);

        if ($response->json('payment_status') !== 'paid') {
            return PlatformPaymentResult::failed(__('لم يكتمل الدفع.'), $invoiceId ?: null);
        }

        return PlatformPaymentResult::paid(
            $invoiceId,
            Money::fromMinor((int) $response->json('amount_total'), strtoupper((string) $response->json('currency'))),
            $sessionId,
        );
    }

    private function lineTitle(Invoice $invoice): string
    {
        $line = $invoice->lines[0]['title'] ?? [];
        $name = is_array($line) ? ($line[app()->getLocale()] ?? $line['ar'] ?? null) : $line;

        return trim(($name ?: __('اشتراك')).' — '.$invoice->number);
    }
}
