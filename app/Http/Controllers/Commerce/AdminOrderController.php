<?php

declare(strict_types=1);

namespace App\Http\Controllers\Commerce;

use App\Core\Support\Money;
use App\Modules\Commerce\Actions\GenerateCodes;
use App\Modules\Commerce\Actions\RecordOrderPayment;
use App\Modules\Commerce\Actions\RefundOrder;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\RechargeBatch;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Lms\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminOrderController
{
    public function show(string $id): View
    {
        return view('commerce.admin-order', [
            'order' => Order::with(['items', 'payments', 'refunds', 'user'])->findOrFail($id),
        ]);
    }

    /** تسجيل دفعة يدوية — التحويل البنكي والنقدي يصلان خارج أي بوابة. */
    public function pay(Request $request, string $id, RecordOrderPayment $action): RedirectResponse
    {
        $order = Order::findOrFail($id);

        $input = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'gateway' => ['required', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $action->handle(
            $order,
            Money::fromDecimal($input['amount'], $order->currency),
            $input['gateway'],
            $input['reference'] ?? null,
        );

        return back()->with('status', __('سُجّلت الدفعة وفُتح ما اشتُري.'));
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $order = Order::findOrFail($id);

        abort_if($order->isPaid(), 409, __('لا يُلغى طلب مدفوع — استخدم الاسترداد.'));

        $order->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $request->input('reason'),
        ])->save();

        return back()->with('status', __('أُلغي الطلب.'));
    }

    public function refund(Request $request, string $id, RefundOrder $action): RedirectResponse
    {
        $order = Order::findOrFail($id);

        try {
            $refund = $action->request($order, $request->user(), $request->input('reason'));
            $action->approve($refund, $request->user(), __('استرداد من اللوحة'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return back()->with('status', __('نُفِّذ الاسترداد وسُحب الوصول.'));
    }

    public function handleRefund(Request $request, string $refundId, RefundOrder $action): RedirectResponse
    {
        $refund = Refund::findOrFail($refundId);

        $input = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $input['decision'] === 'approve'
            ? $action->approve($refund, $request->user(), $input['note'] ?? null)
            : $action->reject($refund, $request->user(), $input['note'] ?? null);

        return back()->with('status', __('حُسم طلب الاسترداد.'));
    }

    public function codes(): View
    {
        return view('commerce.codes', [
            'batches' => RechargeBatch::withCount('codes')->latest()->limit(30)->get(),
            'courses' => Course::where('status', 'published')->get(),
        ]);
    }

    public function generateCodes(Request $request, GenerateCodes $action): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'type' => ['required', 'string', 'in:wallet,course,bundle'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        if ($input['type'] !== 'wallet' && blank($input['course_id'] ?? null)) {
            return back()->withErrors(['course_id' => __('كود فتح الكورس يحتاج كورساً.')])->withInput();
        }

        $currency = (string) (tenant('currency') ?? 'EGP');

        $batch = $action->handle([
            'name' => $input['name'],
            'quantity' => (int) $input['quantity'],
            'type' => $input['type'],
            'currency' => $currency,
            'value_minor' => Money::fromDecimal((string) ($input['value'] ?? 0), $currency)->minor,
            'course_id' => $input['course_id'] ?? null,
            'expires_at' => $input['expires_at'] ?? null,
        ], $request->user());

        return redirect(url('/admin/recharge-codes?filter[batch]='.$batch->id))
            ->with('status', __('وُلِّدت :n كود.', ['n' => $batch->quantity]));
    }

    /** تصدير الدفعة نصّاً للطباعة — كود في كل سطر. */
    public function exportBatch(string $batchId): StreamedResponse
    {
        $batch = RechargeBatch::findOrFail($batchId);

        return response()->streamDownload(function () use ($batch): void {
            foreach ($batch->codes()->cursor() as $code) {
                echo $code->code."\n";
            }
        }, 'codes-'.$batch->id.'.txt', ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
