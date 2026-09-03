<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Modules\Commerce\Models\InstructorEarning;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;

/**
 * تقييد عمولة المدرّس عند البيع.
 *
 * تُقيَّد فوراً كي يرى المدرّس ما استحقّه لحظة البيع، لكنها لا
 * تنضج للتحويل قبل انقضاء مهلة الاسترداد — وإلا حُوِّل مبلغ ثم
 * استُردّ الطلب فصار على المنصة دَينٌ لا يُسترجع.
 */
final class RecordEarnings
{
    public function handle(Order $order): void
    {
        if (! $order->isPaid()) {
            return;
        }

        $matureAfter = (int) setting('commerce.refund_days', 14);

        foreach ($order->items()->whereNotNull('instructor_id')->get() as $item) {
            if ($item->commission_minor <= 0) {
                continue;
            }

            InstructorEarning::firstOrCreate(
                ['order_item_id' => $item->getKey()],
                [
                    'instructor_id' => $item->instructor_id,
                    'currency' => $order->currency,
                    'amount_minor' => (int) $item->commission_minor,
                    'rate' => $this->rateFor($item),
                    'status' => 'available',
                    'available_at' => $order->paid_at?->copy()->addDays($matureAfter) ?? now()->addDays($matureAfter),
                ],
            );
        }
    }

    /** الاسترداد يُرجِع العمولة قيداً سالباً، ولا يمحو الأصل. */
    public function reverse(Order $order): void
    {
        foreach ($order->items()->whereNotNull('instructor_id')->get() as $item) {
            $earning = InstructorEarning::where('order_item_id', $item->getKey())->first();

            if ($earning === null || $earning->status === 'reversed') {
                continue;
            }

            if ($earning->status === 'paid') {
                // حُوِّل بالفعل: نُقيّد خصماً يُسوَّى من تحويل قادم
                InstructorEarning::create([
                    'instructor_id' => $earning->instructor_id,
                    'currency' => $earning->currency,
                    'amount_minor' => -$earning->amount_minor,
                    'status' => 'available',
                    'available_at' => now(),
                ]);
            }

            $earning->forceFill(['status' => 'reversed'])->save();
        }
    }

    private function rateFor(OrderItem $item): float
    {
        return $item->total_minor > 0
            ? round($item->commission_minor / $item->total_minor * 100, 2)
            : 0.0;
    }
}
