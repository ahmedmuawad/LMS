<?php

declare(strict_types=1);

namespace App\Core\Billing\Actions;

use App\Core\Audit\Audit;
use App\Core\Billing\Models\Invoice;
use App\Core\Billing\Models\InvoicePayment;
use App\Core\Support\Money;
use App\Core\Tenancy\Models\Tenant;

/**
 * تسجيل دفعة على فاتورة.
 *
 * الدفع الجزئي مقبول ولا يُقفل الفاتورة؛ الفاتورة تُقفل فقط
 * حين يبلغ المدفوع إجماليها. وسداد فاتورة متعثّرة يُعيد المشترك
 * إلى الخدمة فوراً — لا ننتظر دورة ليلية بينما هو دفع الآن.
 */
final class RecordPayment
{
    public function handle(Invoice $invoice, Money $amount, string $gateway, ?string $reference = null): InvoicePayment
    {
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'gateway' => $gateway,
            'reference' => $reference,
            'currency' => $amount->currency,
            'amount_minor' => $amount->minor,
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);

        $paid = $invoice->paid_minor + $amount->minor;
        $settled = $paid >= $invoice->total_minor;

        $invoice->forceFill([
            'paid_minor' => $paid,
            'status' => $settled ? 'paid' : $invoice->status,
            'paid_at' => $settled ? now() : null,
        ])->save();

        if ($settled) {
            $this->reinstate($invoice);
        }

        Audit::record('invoice.paid', $invoice->tenant_id, $invoice, [
            'invoice' => $invoice->number,
            'amount' => $amount->format(),
            'gateway' => $gateway,
            'settled' => $settled,
        ]);

        return $payment;
    }

    /**
     * السداد ينقل المشترك إلى الخدمة الكاملة فوراً.
     *
     * وهذا يشمل من دفع وهو في تجربته: كان يبقى «تجريبياً» بلافتة
     * «بقي كذا يوماً» رغم أنه سدّد فاتورته كاملة، حتى تمرّ الدورة
     * الليلية — فيرى المشترك أن دفعه لم يُغيّر شيئاً، وهو أسوأ ما
     * يراه بعد الدفع مباشرة. التجربة تنتهي بالدفع لا بالتاريخ.
     */
    private function reinstate(Invoice $invoice): void
    {
        $subscription = $invoice->subscription;

        if ($subscription !== null && in_array($subscription->status, ['trialing', 'past_due', 'paused'], true)) {
            $subscription->forceFill([
                'status' => 'active',
                'failed_charges' => 0,
                'trial_ends_at' => $subscription->status === 'trialing' ? now() : $subscription->trial_ends_at,
            ])->save();
        }

        $tenant = Tenant::find($invoice->tenant_id);

        if ($tenant !== null && in_array($tenant->status, ['trialing', 'past_due', 'suspended'], true)) {
            $tenant->forceFill([
                'status' => 'active',
                'suspended_at' => null,
                'trial_ends_at' => $tenant->status === 'trialing' ? null : $tenant->trial_ends_at,
            ])->save();
        }
    }
}
