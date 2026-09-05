<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Jobs;

use App\Modules\Webhooks\Models\WebhookDelivery;
use App\Modules\Webhooks\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * تسليم حدثٍ واحد إلى وجهةٍ واحدة.
 *
 * ## في الطابور لا في الطلب
 *
 * وجهةٌ بطيئة تعني طالباً ينتظر عشرين ثانية بعد ضغطه «سجّلني».
 * وربطُ سرعة المنصّة بسرعة خادمٍ لا نملكه خطأٌ لا يُصلَح بمهلةٍ
 * أقصر.
 *
 * ## والفشل يُعاد لا يُنسى
 *
 * خادمُ المستقبِل يسقط دقيقةً في الأسبوع؛ وحدثٌ ضاع لأن السقوط
 * صادف لحظته هو ما يجعل التكامل غير موثوق. والمحاولات تتباعد كي
 * لا نضرب خادماً متعثّراً بينما يحاول النهوض.
 */
final class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     | عدد المحاولات من إعداد المشترك.
     |
     | «محاولات إعادة الإرسال» حقلٌ في شاشة التكاملات منذ البداية
     | ولا شيء يقرؤه. ويُقرأ هنا في المُنشئ لا في `handle`: الطابور
     | يقرأ الخاصيّة لحظة الجدولة لا لحظة التنفيذ.
     */
    public int $tries = 4;

    /** دقيقة، ثم خمس، ثم نصف ساعة — يتّسع لانقطاعٍ قصير */
    public array $backoff = [60, 300, 1800];

    /** مهلةٌ تكفي خادماً بطيئاً ولا تحبس عاملاً */
    public int $timeout = 25;

    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public readonly int $endpointId,
        public readonly string $event,
        public readonly array $payload,
    ) {
        // «إعادة الإرسال» عدد الإعادات، والمحاولة الأولى ليست إعادة
        $this->tries = max(1, (int) setting('integrations.webhook_retries', 3) + 1);
    }

    public function handle(): void
    {
        $endpoint = WebhookEndpoint::find($this->endpointId);

        // حُذفت الوجهة أو أُوقفت بعد جدولة المهمة: لا شيء يُسلَّم
        if ($endpoint === null || ! $endpoint->wants($this->event)) {
            return;
        }

        $timestamp = now()->getTimestamp();

        $body = (string) json_encode([
            'event' => $this->event,
            'occurred_at' => now()->toIso8601String(),
            'site' => site_name(),
            'data' => $this->payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $delivery = WebhookDelivery::create([
            'endpoint_id' => $endpoint->getKey(),
            'event' => $this->event,
            'payload' => $this->payload,
            'attempt' => $this->attempts(),
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Usos-Webhooks/1.0',

                /*
                 | التوقيع والطابع منفصلان في الترويسات.
                 |
                 | المستقبِل يرفض ما مضى على طابعه أكثر من خمس دقائق،
                 | فلا يُعاد تشغيل طلبٍ التُقط بالأمس.
                 */
                'X-Usos-Event' => $this->event,
                'X-Usos-Timestamp' => (string) $timestamp,
                'X-Usos-Signature' => 'sha256='.$endpoint->sign($body, $timestamp),
            ])->timeout($this->timeout)->withBody($body, 'application/json')->post($endpoint->url);

            $delivery->forceFill([
                'status_code' => $response->status(),
                'response' => mb_substr($response->body(), 0, WebhookDelivery::RESPONSE_LIMIT),
                'delivered_at' => now(),
            ])->save();

            if ($response->successful()) {
                $endpoint->forceFill([
                    'last_status' => $response->status(),
                    'last_delivered_at' => now(),
                    'consecutive_failures' => 0,
                ])->save();

                return;
            }

            $this->fail($endpoint, __('ردّ المستقبِل بالرمز :code', ['code' => $response->status()]));
        } catch (Throwable $e) {
            $delivery->forceFill([
                'error' => mb_substr($e->getMessage(), 0, WebhookDelivery::RESPONSE_LIMIT),
            ])->save();

            $this->fail($endpoint, $e->getMessage());
        }
    }

    /**
     * يُسجّل الإخفاق، ويُوقف الوجهة إن طال موتُها.
     *
     * رابطٌ حُذف عند المشترك يُعيد كلَّ حدثٍ أربعَ محاولات إلى
     * الأبد، فيمتلئ الطابور بما لا يصل ويتأخّر ما يصل.
     */
    private function fail(WebhookEndpoint $endpoint, string $reason): void
    {
        $failures = (int) $endpoint->consecutive_failures + 1;

        $endpoint->forceFill([
            'consecutive_failures' => $failures,
            'disabled_at' => $failures >= WebhookEndpoint::FAILURE_LIMIT ? now() : null,
        ])->save();

        // الاستثناء يجعل الطابور يُعيد المحاولة بالتباعد المُعلَن
        throw new \RuntimeException($reason);
    }
}
