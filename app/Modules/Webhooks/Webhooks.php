<?php

declare(strict_types=1);

namespace App\Modules\Webhooks;

use App\Modules\Webhooks\Jobs\DeliverWebhook;
use App\Modules\Webhooks\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ناقلُ الأحداث إلى الخارج.
 *
 * ## لا كتالوج ثانياً
 *
 * أحداث الـWebhooks هي أحداث الإشعارات نفسها (`config/notification-events`):
 * ثمانيةٌ وأربعون حدثاً معرَّفة ومترجَمة ومربوطة بموديولاتها. وقائمةٌ
 * ثانية تعني حدثاً يُضاف إلى إحداهما وينسى في الأخرى.
 *
 * ## والنداء من `Notifier` وحده
 *
 * كل ما يحدث في المنصّة يمرّ به: تسجيل، ودفع، وشهادة، وغياب. فنقطةُ
 * إطلاقٍ واحدة تغطّي الكل، بدل زرع نداءٍ في ثلاثين موضعاً — تُنسى
 * منها عشرة.
 */
final class Webhooks
{
    /**
     * يُطلق حدثاً إلى كل وجهةٍ تشترك فيه.
     *
     * ولا يرمي أبداً: عطلٌ في تكاملٍ اختياري لا يُسقط تسجيل طالب.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fire(string $event, array $payload = []): void
    {
        try {
            if (tenant() === null || ! setting('integrations.webhooks_enabled', false)) {
                return;
            }

            $endpoints = WebhookEndpoint::query()
                ->where('is_active', true)
                ->whereNull('disabled_at')
                ->get()
                ->filter(fn (WebhookEndpoint $endpoint): bool => $endpoint->wants($event));

            foreach ($endpoints as $endpoint) {
                DeliverWebhook::dispatch($endpoint->getKey(), $event, $payload);
            }
        } catch (Throwable $e) {
            /*
             | الجدول قد لا يكون مُهاجَراً بعد.
             |
             | مشتركٌ قديم لم تصله الهجرة بعدُ يجب أن يعمل موقعه؛
             | وتكاملٌ لم يُطلب لا يستحقّ أن يُسقط صفحة.
             */
            Log::warning('تعذّر إطلاق webhook: '.$e->getMessage(), ['event' => $event]);
        }
    }
}
