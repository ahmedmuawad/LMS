<?php

declare(strict_types=1);

namespace App\Modules\Center\Actions;

use App\Core\Support\Money;
use App\Models\User;
use App\Modules\Center\Models\Cashbox;
use App\Modules\Center\Models\CashMovement;
use App\Modules\Center\Models\Invoice;
use App\Modules\Center\Models\Payment;
use App\Modules\Center\Models\Student;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * تحصيل قسط.
 *
 * كل جنيه يدخل الدرج يقابله إيصال مرقّم وحركة خزنة. بدون هذا
 * الربط تصبح «الفلوس اللي في الدرج مش مظبوطة» سؤالاً بلا جواب.
 */
final class CollectPayment
{
    public function handle(
        Student $student,
        Money $amount,
        ?Invoice $invoice = null,
        ?Cashbox $cashbox = null,
        string $method = 'cash',
        ?User $receiver = null,
        ?string $reference = null,
    ): Payment {
        if (! $amount->isPositive()) {
            throw new RuntimeException(__('المبلغ يجب أن يكون أكبر من صفر.'));
        }

        return DB::transaction(function () use ($student, $amount, $invoice, $cashbox, $method, $receiver, $reference): Payment {
            $payment = Payment::create([
                'receipt_no' => $this->nextReceipt(),
                'invoice_id' => $invoice?->getKey(),
                'student_id' => $student->getKey(),
                'cashbox_id' => $cashbox?->getKey(),
                'currency' => $amount->currency,
                'amount_minor' => $amount->minor,
                'method' => $method,
                'received_by' => $receiver?->getKey(),
                'reference' => $reference,
                'paid_at' => now(),
            ]);

            if ($invoice !== null) {
                $paid = (int) $invoice->paid_minor + $amount->minor;

                $invoice->forceFill([
                    'paid_minor' => $paid,
                    'status' => match (true) {
                        $paid >= (int) $invoice->total_minor => 'paid',
                        $paid > 0 => 'partial',
                        default => 'unpaid',
                    },
                ])->save();
            }

            // النقد وحده يدخل الخزنة؛ التحويل والأونلاين لهما مسارهما
            if ($cashbox !== null && in_array($method, ['cash', 'card'], true)) {
                $this->recordMovement($cashbox, $amount, $payment->receipt_no, $receiver);
            }

            return $payment;
        });
    }

    private function recordMovement(Cashbox $cashbox, Money $amount, string $reference, ?User $recorder): void
    {
        $locked = Cashbox::whereKey($cashbox->getKey())->lockForUpdate()->firstOrFail();
        $balance = (int) $locked->balance_minor + $amount->minor;

        CashMovement::create([
            'cashbox_id' => $locked->getKey(),
            'type' => 'in',
            'currency' => $amount->currency,
            'amount_minor' => $amount->minor,
            'balance_after_minor' => $balance,
            'category' => 'tuition',
            'reference' => $reference,
            'recorded_by' => $recorder?->getKey(),
        ]);

        $locked->forceFill(['balance_minor' => $balance])->save();
    }

    private function nextReceipt(): string
    {
        $prefix = 'R-'.now()->format('Ym').'-';

        $last = Payment::where('receipt_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('receipt_no')
            ->value('receipt_no');

        $sequence = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
