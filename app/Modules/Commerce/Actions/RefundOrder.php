<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Enrollment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * الاسترداد.
 *
 * سياسة واضحة تُفحص آلياً: مهلة بالأيام وحدّ لنسبة المشاهدة.
 * من شاهد الكورس كاملاً ثم طلب استرداده لم يشترِ — استعار.
 */
final class RefundOrder
{
    public function request(Order $order, ?User $user, ?string $reason = null): Refund
    {
        if (! $order->isRefundable()) {
            throw new RuntimeException(__('هذا الطلب غير قابل للاسترداد.'));
        }

        $this->assertWithinWindow($order);
        $this->assertNotConsumed($order);

        return Refund::create([
            'order_id' => $order->getKey(),
            'currency' => $order->currency,
            'amount_minor' => (int) $order->total_minor - (int) $order->refunded_minor,
            'status' => setting('commerce.refund_mode', 'manual') === 'auto' ? 'approved' : 'requested',
            'reason' => $reason,
            'requested_by' => $user?->getKey(),
        ]);
    }

    public function approve(Refund $refund, ?User $handler = null, ?string $note = null): Refund
    {
        return DB::transaction(function () use ($refund, $handler, $note): Refund {
            $order = $refund->order;
            $amount = $refund->amount();

            $refund->forceFill([
                'status' => 'processed',
                'admin_note' => $note,
                'handled_by' => $handler?->getKey(),
                'handled_at' => now(),
            ])->save();

            $refunded = (int) $order->refunded_minor + $amount->minor;

            $order->forceFill([
                'refunded_minor' => $refunded,
                'status' => $refunded >= (int) $order->total_minor ? 'refunded' : $order->status,
            ])->save();

            if ($order->status === 'refunded') {
                $this->revokeAccess($order);
            }

            return $refund->refresh();
        });
    }

    public function reject(Refund $refund, ?User $handler = null, ?string $note = null): Refund
    {
        $refund->forceFill([
            'status' => 'rejected',
            'admin_note' => $note,
            'handled_by' => $handler?->getKey(),
            'handled_at' => now(),
        ])->save();

        return $refund;
    }

    private function assertWithinWindow(Order $order): void
    {
        $days = (int) setting('commerce.refund_days', 14);

        if ($days > 0 && $order->paid_at !== null && $order->paid_at->addDays($days)->isPast()) {
            throw new RuntimeException(__('انقضت مهلة الاسترداد (:days يوماً).', ['days' => $days]));
        }
    }

    /** من استهلك أكثر من الحدّ المسموح لم يعد مسترِدّاً. */
    private function assertNotConsumed(Order $order): void
    {
        $limit = (int) setting('commerce.refund_max_progress', 20);

        if ($limit <= 0 || $order->user_id === null) {
            return;
        }

        $courseIds = $order->items()
            ->where('purchasable_type', Course::class)
            ->pluck('purchasable_id')
            ->filter();

        if ($courseIds->isEmpty()) {
            return;
        }

        $maxProgress = (int) Enrollment::where('user_id', $order->user_id)
            ->whereIn('course_id', $courseIds)
            ->max('progress_percent');

        if ($maxProgress > $limit) {
            throw new RuntimeException(__('تجاوزت نسبة المشاهدة المسموح بها للاسترداد (:limit%).', ['limit' => $limit]));
        }
    }

    /**
     * سحب الوصول لا حذف السجل: درجات الطالب وسجلّه يبقيان،
     * فلو عاد واشترى وجد ما تركه.
     */
    private function revokeAccess(Order $order): void
    {
        $courseIds = $order->items()
            ->where('purchasable_type', Course::class)
            ->pluck('purchasable_id')
            ->filter();

        if ($courseIds->isEmpty() || $order->user_id === null) {
            return;
        }

        Enrollment::where('user_id', $order->user_id)
            ->whereIn('course_id', $courseIds)
            ->update(['status' => 'refunded']);
    }
}
