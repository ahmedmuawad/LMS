<?php

declare(strict_types=1);

namespace App\Http\Controllers\Commerce;

use App\Modules\Commerce\Actions\RecordOrderPayment;
use App\Modules\Commerce\Gateways\GatewayManager;
use App\Modules\Commerce\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * ردّ البوابات.
 *
 * البوابة تُعيد الإرسال حتى نردّ بنجاح، فكل شيء هنا يحتمل التكرار:
 * ردٌّ يصل مرتين لا يُسجَّل دفعتين ولا يفتح المحتوى مرتين.
 */
final class WebhookController
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly RecordOrderPayment $payments,
    ) {}

    public function __invoke(Request $request, string $gateway): Response
    {
        if (! $this->gateways->has($gateway)) {
            return response('unknown gateway', 404);
        }

        $result = $this->gateways->resolve($gateway)->handleCallback($request);

        if ($result === null) {
            // توقيع خاطئ أو ردّ غير مفهوم: لا نغيّر شيئاً ولا نكشف السبب
            return response('ignored', 202);
        }

        $order = Order::where('number', $result->orderNumber)->first();

        if ($order === null) {
            Log::warning('ردّ بوابة لطلب غير موجود', ['gateway' => $gateway, 'order' => $result->orderNumber]);

            return response('unknown order', 202);
        }

        if (! $result->successful) {
            $this->payments->fail($order, $gateway, $result->failureReason ?? 'declined', $result->raw);

            return response('ok', 200);
        }

        // التكرار: نفس المرجع لا يُسجَّل مرتين
        $seen = $order->payments()
            ->where('gateway', $gateway)
            ->where('gateway_ref', $result->reference)
            ->where('status', 'captured')
            ->exists();

        if (! $seen) {
            $this->payments->handle($order, $result->amount, $gateway, $result->reference, $result->raw);
        }

        return response('ok', 200);
    }

    /** عودة المستخدم من صفحة البوابة — العرض فقط، والاعتماد على الـ webhook. */
    public function return(Request $request, string $gateway): RedirectResponse
    {
        $number = (string) $request->query('order', '');
        $order = Order::where('number', $number)->first();

        if ($order === null) {
            return redirect(url('/'))->withErrors(['order' => __('لم نجد هذا الطلب.')]);
        }

        return redirect(url('/orders/'.$order->number))->with(
            'status',
            $order->isPaid()
                ? __('تم الدفع بنجاح.')
                : __('استُلم طلبك — سنؤكّد الدفع خلال لحظات.'),
        );
    }
}
