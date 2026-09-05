<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Gateways;

use App\Core\Support\Money;
use App\Modules\Commerce\Actions\RecordDispute;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يتعرّف على إخطار النزاع في ردّ البوابة.
 *
 * ## ولماذا فُصل عن مسار الدفع
 *
 * إخطارُ النزاع ليس نتيجةَ دفع: لا طلبَ يُحدَّث ولا مبلغَ يُحصَّل —
 * بل مالٌ سُحب ومهلةٌ بدأت. وتمريرُه على مسار الدفع يجعله «ردّاً غير
 * مفهوم» يُهمَل بصمت، فتفوت المهلة ويخسر المشترك المال.
 *
 * ## وStripe وحدها تُقرَأ تلقائياً — والباقي يُسجَّل يدوياً
 *
 * شكلُ إخطار Stripe موثَّق وثابت، ونقرؤه بيقين. أمّا Paymob وFawry
 * فتُخطران بالبريد لا بشكلٍ موثَّقٍ نستطيع فحصه، فيسجّل المشترك
 * النزاع بيده من الشاشة.
 *
 * وادّعاءُ قراءةٍ تلقائية لا نضمنها أسوأ من الاعتراف: من يظنّها
 * تعمل لا يفتح الشاشة أصلاً.
 */
final class DisputeListener
{
    /** أحداث Stripe التي تعني نزاعاً */
    private const STRIPE_EVENTS = [
        'charge.dispute.created',
        'charge.dispute.updated',
        'charge.dispute.funds_withdrawn',
    ];

    /** هل كان هذا الطلب إخطار نزاع؟ */
    public function fromRequest(string $gateway, Request $request): bool
    {
        if ($gateway !== 'stripe') {
            return false;
        }

        $type = (string) $request->input('type', '');

        if (! in_array($type, self::STRIPE_EVENTS, true)) {
            return false;
        }

        try {
            $object = (array) $request->input('data.object', []);

            app(RecordDispute::class)->handle(
                gateway: $gateway,
                reference: (string) ($object['id'] ?? ''),
                amount: Money::fromMinor(
                    (int) ($object['amount'] ?? 0),
                    mb_strtoupper((string) ($object['currency'] ?? 'usd')),
                ),
                orderNumber: $this->orderNumber($object),
                reason: (string) ($object['reason'] ?? ''),
                dueBy: $this->dueBy($object),
                raw: $object,
            );

            return true;
        } catch (Throwable $e) {
            /*
             | وفشلُ التسجيل لا يُعيد خطأً للبوابة.
             |
             | البوابة تُعيد الإرسال حتى تُجاب، وإخطارٌ لا نفهمه يُعاد
             | إلى الأبد. ويُسجَّل في اللوج كي يُرى.
             */
            Log::error('تعذّر تسجيل نزاع: '.$e->getMessage(), ['gateway' => $gateway]);

            return true;
        }
    }

    /** @param  array<string, mixed>  $object */
    private function orderNumber(array $object): ?string
    {
        $metadata = (array) ($object['metadata'] ?? []);

        return $metadata['order_number'] ?? null;
    }

    /** @param  array<string, mixed>  $object */
    private function dueBy(array $object): ?Carbon
    {
        $due = $object['evidence_details']['due_by'] ?? null;

        return is_numeric($due) ? Carbon::createFromTimestamp((int) $due) : null;
    }
}
