<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Lms\Actions\EnrollStudent;
use App\Modules\Lms\Models\Course;
use Illuminate\Support\Facades\DB;

/**
 * التسليم بعد الدفع.
 *
 * الكورس يُفتح فوراً — الطالب دفع الآن ولا يحتمل انتظار مراجعة.
 * والمنتج المادي ينتظر شحنه، فحالته «قيد التنفيذ» لا «مكتمل».
 */
final class FulfillOrder
{
    public function __construct(private readonly EnrollStudent $enrol) {}

    public function handle(Order $order): Order
    {
        if (! $order->isPaid()) {
            return $order;
        }

        $order->loadMissing('items');

        DB::transaction(function () use ($order): void {
            foreach ($order->items as $item) {
                $this->deliver($order, $item);
            }

            $pending = $order->items()->whereNull('fulfilled_at')->exists();

            $order->forceFill([
                'status' => $pending ? 'processing' : 'completed',
                'completed_at' => $pending ? null : now(),
            ])->save();
        });

        return $order->refresh();
    }

    private function deliver(Order $order, OrderItem $item): void
    {
        if ($item->isFulfilled()) {
            return;
        }

        if ($item->purchasable_type === Course::class && $order->user !== null) {
            $course = Course::find($item->purchasable_id);

            if ($course !== null) {
                $this->enrol->handle($order->user, $course, 'purchase', $item->getKey());
                $item->forceFill(['fulfilled_at' => now()])->save();
            }

            return;
        }

        // ما لا يحتاج شحناً يُسلَّم فوراً؛ والمادي ينتظر مَن يشحنه
        if ($item->product !== null && ! $item->product->requires_shipping) {
            $item->forceFill(['fulfilled_at' => now()])->save();
        }

        $item->product?->increment('sales_count', $item->quantity);
    }
}
