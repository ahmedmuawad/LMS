<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Billing\Actions\RecordPayment;
use App\Core\Billing\Models\Invoice;
use App\Core\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class InvoiceController
{
    public function show(string $id): View
    {
        return view('super-admin.invoice', [
            'invoice' => Invoice::with(['tenant', 'subscription', 'payments'])->findOrFail($id),
        ]);
    }

    /** تسجيل دفعة يدوية — التحويل البنكي والنقدي يصلان إلينا خارج أي بوابة. */
    public function pay(Request $request, string $id, RecordPayment $action): RedirectResponse
    {
        $invoice = Invoice::findOrFail($id);

        $input = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'gateway' => ['required', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $action->handle(
            $invoice,
            Money::fromDecimal($input['amount'], $invoice->currency),
            $input['gateway'],
            $input['reference'] ?? null,
        );

        return back()->with('status', __('تم تسجيل الدفعة.'));
    }

    public function void(string $id): RedirectResponse
    {
        $invoice = Invoice::findOrFail($id);

        abort_if($invoice->status === 'paid', 409, __('لا تُلغى فاتورة مدفوعة — استخدم الاسترداد.'));

        $invoice->forceFill(['status' => 'void'])->save();

        return back()->with('status', __('تم إلغاء الفاتورة.'));
    }
}
