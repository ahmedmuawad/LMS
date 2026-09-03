<?php

declare(strict_types=1);

namespace App\Core\Notifications\Jobs;

use App\Core\Notifications\ChannelRegistry;
use App\Core\Notifications\Delivery;
use App\Core\Notifications\EventCatalogue;
use App\Core\Notifications\Models\NotificationLog;
use App\Core\Notifications\Models\NotificationTemplate;
use App\Core\Notifications\TemplateRenderer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * إرسال رسالة واحدة على قناة واحدة.
 *
 * مهمة لكل قناة لا مهمة لكل حدث: فشل واتساب يجب ألّا يمنع البريد،
 * وإعادة المحاولة يجب أن تعيد القناة الفاشلة وحدها.
 *
 * الطابور يعرف المشترك: stancl تحقن هويته وتعيد بناء السياق قبل
 * التنفيذ، فقاعدة البيانات التي يكتب فيها هي قاعدة صاحب الحدث.
 */
final class SendNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** تباعد متصاعد: مزوّد متعثّر لا يُصلحه إلحاح فوري. */
    public array $backoff = [60, 300];

    /** @param  array<string, mixed>  $data */
    public function __construct(
        public readonly string $event,
        public readonly string $channel,
        public readonly int $userId,
        public readonly array $data = [],
        public readonly ?int $logId = null,
    ) {}

    public function handle(
        EventCatalogue $catalogue,
        ChannelRegistry $channels,
        TemplateRenderer $renderer,
    ): void {
        $event = $catalogue->get($this->event);
        $channel = $channels->get($this->channel);
        $user = User::find($this->userId);

        if ($event === null || $channel === null || $user === null) {
            $this->record('skipped', __('حدث أو قناة أو مستخدم غير موجود'));

            return;
        }

        if (! $channel->isReady()) {
            $this->record('skipped', __('القناة غير مُعدّة'));

            return;
        }

        $template = NotificationTemplate::where('event', $this->event)
            ->where('channel', $this->channel)->first();

        $locale = (string) ($user->locale ?: config('locales.default', 'ar'));

        $rendered = $renderer->render($event, $template, $this->data + $this->siteVariables($user), $locale);

        $delivery = new Delivery(
            event: $event,
            user: $user,
            locale: $locale,
            subject: $rendered['subject'],
            body: $rendered['body'],
            data: $this->data,
            providerTemplate: $template?->provider_template,
        );

        $destination = $channel->destinationFor($delivery);

        if ($destination === null) {
            // لا بريد ولا رقم ولا جهاز: تخطٍّ صريح لا فشل صامت
            $this->record('skipped', __('لا عنوان للمستلم على هذه القناة'));

            return;
        }

        try {
            $providerId = $channel->send($delivery);
        } catch (Throwable $e) {
            $this->record('failed', mb_substr($e->getMessage(), 0, 200), $destination);

            throw $e;
        }

        $this->record('sent', null, $destination, $providerId);
    }

    public function failed(?Throwable $e): void
    {
        $this->record('failed', $e === null ? null : mb_substr($e->getMessage(), 0, 200));
    }

    /**
     * متغيّرات يعرفها كل حدث بلا أن يمرّرها المُستدعي.
     *
     * @return array<string, string>
     */
    private function siteVariables(User $user): array
    {
        $name = (string) $user->name;

        return [
            'name' => $name,
            'first_name' => explode(' ', trim($name))[0] ?: $name,
            'email' => (string) $user->email,
            'site_name' => (string) (setting()->translated('general.site_name') ?: tenant('name') ?? config('app.name')),
            'site_url' => url('/'),
        ];
    }

    private function record(string $status, ?string $reason = null, ?string $destination = null, ?string $providerId = null): void
    {
        $log = $this->logId === null ? null : NotificationLog::find($this->logId);

        $attributes = [
            'status' => $status,
            'reason' => $reason,
            'destination' => $destination,
            'provider_id' => $providerId,
            'sent_at' => $status === 'sent' ? now() : null,
        ];

        if ($log !== null) {
            $log->forceFill($attributes)->save();

            return;
        }

        NotificationLog::create($attributes + [
            'event' => $this->event,
            'channel' => $this->channel,
            'user_id' => $this->userId,
        ]);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['notification', $this->event, $this->channel];
    }
}
