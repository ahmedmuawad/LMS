<?php

declare(strict_types=1);

namespace App\Modules\Growth\Actions;

use App\Core\Support\Money;
use App\Modules\Commerce\Models\Order;
use App\Modules\Growth\Models\Affiliate;
use App\Modules\Growth\Models\AffiliateClick;
use App\Modules\Growth\Models\AffiliateConversion;
use Illuminate\Support\Facades\DB;

/**
 * نسب طلب إلى مسوّق واحتساب عمولته.
 *
 * العمولة تنضج بعد انقضاء مهلة الاسترداد لا لحظة البيع: صرفها فوراً
 * يعني دفع عمولة على بيع يُسترد بعد أسبوع، والاسترداد يقع فعلاً.
 */
final class RecordConversion
{
    public function handle(Order $order, Affiliate $affiliate): ?AffiliateConversion
    {
        if (! (bool) setting('growth.affiliates_enabled', false) || $affiliate->status !== 'active') {
            return null;
        }

        // المسوّق لا يُعمَّل على شراء نفسه
        if ($order->user_id !== null && (int) $affiliate->user_id === (int) $order->user_id) {
            return null;
        }

        $existing = AffiliateConversion::where('affiliate_id', $affiliate->getKey())
            ->where('order_id', $order->getKey())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $base = $this->commissionable($order);
        $commission = $this->commissionFor($affiliate, $base);

        if ($commission->minor <= 0) {
            return null;
        }

        return DB::transaction(function () use ($order, $affiliate, $base, $commission): AffiliateConversion {
            $hold = max(0, (int) setting('growth.affiliates_hold_days', 14));

            $conversion = AffiliateConversion::create([
                'affiliate_id' => $affiliate->getKey(),
                'order_id' => $order->getKey(),
                'user_id' => $order->user_id,
                'currency' => $base->currency,
                'amount_minor' => $base->minor,
                'commission_minor' => $commission->minor,
                'status' => 'pending',
                'matured_at' => now()->addDays($hold),
            ]);

            $affiliate->increment('conversions_count');

            AffiliateClick::where('affiliate_id', $affiliate->getKey())
                ->whereNull('converted_at')
                ->latest('id')
                ->limit(1)
                ->update(['converted_at' => now()]);

            return $conversion;
        });
    }

    /** ما نضج ولم يُرفض يصير مستحقّاً — يُنادى من المهمة المجدولة. */
    public function matureAll(): int
    {
        $ready = AffiliateConversion::where('status', 'pending')
            ->whereNotNull('matured_at')
            ->where('matured_at', '<=', now())
            ->get();

        foreach ($ready as $conversion) {
            DB::transaction(function () use ($conversion): void {
                $conversion->forceFill(['status' => 'approved'])->save();

                $affiliate = $conversion->affiliate;

                if ($affiliate === null) {
                    return;
                }

                $affiliate->increment('earned_minor', (int) $conversion->commission_minor);

                if ($affiliate->user !== null) {
                    notify('affiliate.conversion', $affiliate->user, [
                        'amount' => $conversion->amount()->format(),
                        'commission' => $conversion->commission()->format(),
                        'order_number' => (string) ($conversion->order_id ?? ''),
                        'url' => url('/affiliate'),
                    ]);
                }
            });
        }

        return $ready->count();
    }

    /** الاسترداد يسحب العمولة: البيع الذي رُدّ ثمنه لم يحدث. */
    public function reject(AffiliateConversion $conversion, string $reason): AffiliateConversion
    {
        if ($conversion->status === 'paid') {
            // المدفوع لا يُسحب من الجدول بل يُخصم من الرصيد القادم
            return $conversion;
        }

        DB::transaction(function () use ($conversion, $reason): void {
            $wasApproved = $conversion->status === 'approved';

            $conversion->forceFill(['status' => 'rejected', 'reject_reason' => $reason])->save();

            if ($wasApproved && $conversion->affiliate !== null) {
                $conversion->affiliate->decrement('earned_minor', (int) $conversion->commission_minor);
            }
        });

        return $conversion->refresh();
    }

    /**
     * الأساس الذي تُحسب عليه العمولة.
     *
     * الشحن والضريبة ليسا ربحاً للمنصّة، واحتسابهما يعني عمولة على
     * مال يمرّ عبرنا إلى غيرنا.
     */
    private function commissionable(Order $order): Money
    {
        $base = (int) $order->total_minor
            - (int) ($order->shipping_minor ?? 0)
            - (int) ($order->tax_minor ?? 0);

        return Money::fromMinor(max(0, $base), (string) $order->currency);
    }

    private function commissionFor(Affiliate $affiliate, Money $base): Money
    {
        if ($affiliate->commission_type === 'fixed') {
            return Money::fromMinor((int) $affiliate->fixed_minor, $base->currency);
        }

        return Money::fromMinor((int) round($base->minor * $affiliate->rate() / 100), $base->currency);
    }
}
