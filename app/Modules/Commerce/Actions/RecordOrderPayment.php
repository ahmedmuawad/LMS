<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Core\Support\Money;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Payment;

/**
 * تسجيل دفعة على طلب.
 *
 * الطلب يُعدّ مدفوعاً بمجموع الدفعات المحصّلة لا بحالة واحدة:
 * الدفع الجزئي والتحويل البنكي والمحفظة قد تجتمع على طلب واحد.
 */
final class RecordOrderPayment
{
    public function __construct(
        private readonly FulfillOrder $fulfil,
        private readonly RecordEarnings $earnings,
    ) {}

    public function handle(
        Order $order,
        Money $amount,
        string $gateway,
        ?string $reference = null,
        ?array $response = null,
    ): Payment {
        $payment = Payment::create([
            'order_id' => $order->getKey(),
            'gateway' => $gateway,
            'gateway_ref' => $reference,
            'currency' => $amount->currency,
            'amount_minor' => $amount->minor,
            'status' => 'captured',
            'raw_response' => $response,
            'paid_at' => now(),
        ]);

        if ($order->refresh()->paid()->minor >= (int) $order->total_minor) {
            $order->forceFill([
                'status' => 'paid',
                'paid_at' => $order->paid_at ?? now(),
                'gateway' => $gateway,
            ])->save();

            $this->fulfil->handle($order->refresh());

            // العمولة تُقيَّد عند البيع كي يراها المدرّس فوراً
            $this->earnings->handle($order->refresh());

            if ($order->user !== null) {
                notify('commerce.payment_received', $order->user, [
                    'order_number' => (string) $order->number,
                    'amount' => $amount->format(),
                    'method' => __($gateway),
                    'invoice_url' => url('/orders/'.$order->number),
                    'url' => url('/orders/'.$order->number),
                ]);
            }
        }

        return $payment;
    }

    public function fail(Order $order, string $gateway, string $reason, ?array $response = null): Payment
    {
        if ($order->user !== null) {
            notify('commerce.payment_failed', $order->user, [
                'order_number' => (string) $order->number,
                'amount' => $order->total()->format(),
                'reason' => $reason,
                'retry_url' => url('/orders/'.$order->number),
                'url' => url('/orders/'.$order->number),
            ]);
        }

        return Payment::create([
            'order_id' => $order->getKey(),
            'gateway' => $gateway,
            'currency' => $order->currency,
            'amount_minor' => (int) $order->total_minor,
            'status' => 'failed',
            'failure_reason' => mb_substr($reason, 0, 255),
            'raw_response' => $response,
        ]);
    }
}
