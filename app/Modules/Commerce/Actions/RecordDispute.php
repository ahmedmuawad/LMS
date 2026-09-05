<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Core\Support\Money;
use App\Modules\Commerce\Models\Dispute;
use App\Modules\Commerce\Models\Order;
use App\Modules\Webhooks\Webhooks;
use Illuminate\Support\Carbon;

/**
 * يُسجّل نزاعاً وصل من البوابة.
 *
 * ## ولا يُسجَّل مرّتين
 *
 * البوابات تُعيد إرسال الإخطار حتى نُجيب بنجاح؛ وبلا مطابقةٍ على
 * مرجعها يصير النزاع الواحد خمسة صفوفٍ في الشاشة، ويظنّ المشترك
 * أن عليه خمس مهل.
 *
 * ## ويُطلق حدثاً
 *
 * لأن المهلة أيامٌ معدودة: من لا يفتح لوحته يومياً يجب أن يصله
 * الخبر في بريده وواتسابه — والنزاع أعجلُ ما يقع في التجارة.
 */
final class RecordDispute
{
    /** كم يوماً تُمهل البوابات عادةً حين لا تُخبرنا بالمهلة */
    private const DEFAULT_DAYS = 7;

    /**
     * @param  array<string, mixed>  $raw
     */
    public function handle(
        string $gateway,
        string $reference,
        Money $amount,
        ?string $orderNumber = null,
        ?string $reason = null,
        ?Carbon $dueBy = null,
        array $raw = [],
    ): Dispute {
        $order = $orderNumber === null ? null : Order::where('number', $orderNumber)->first();

        $dispute = Dispute::updateOrCreate(
            ['gateway_ref' => $reference],
            [
                'order_id' => $order?->getKey(),
                'gateway' => $gateway,
                'currency' => $amount->currency,
                'amount_minor' => $amount->minor,
                'reason' => $reason,

                /*
                 | المهلة تُفترَض إن لم تُذكَر.
                 |
                 | و`null` تجعل السطر يهبط إلى آخر القائمة المرتَّبة
                 | بالمهلة — فيختفي أعجلُ ما فيها.
                 */
                'due_by' => $dueBy ?? now()->addDays(self::DEFAULT_DAYS),

                'raw' => $raw,
            ],
        );

        // لا يُطلق إلا عند أوّل تسجيل: الإخطار المعاد لا يُعيد الإنذار
        if ($dispute->wasRecentlyCreated) {
            app(Webhooks::class)->fire('commerce.dispute_opened', [
                'gateway' => $gateway,
                'reference' => $reference,
                'amount' => $amount->toDecimal(),
                'currency' => $amount->currency,
                'order' => $orderNumber,
                'due_by' => $dispute->due_by?->toIso8601String(),
            ]);
        }

        return $dispute;
    }
}
