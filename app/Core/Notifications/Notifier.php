<?php

declare(strict_types=1);

namespace App\Core\Notifications;

use App\Core\Notifications\Jobs\SendNotification;
use App\Core\Notifications\Models\NotificationLog;
use App\Core\Notifications\Models\NotificationPreference;
use App\Core\Notifications\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * نقطة الدخول الوحيدة لإرسال أي إشعار.
 *
 *   notify('lms.enrolled', $student, ['course_title' => …]);
 *
 * القرار هنا لا في مكان الاستدعاء: أي قناة يسمح بها الحدث، وأيّها
 * فعّلها المشترك، وأيّها لم يُطفئها المستخدم، وهل الوقت وقت هدوء.
 * تفريق هذا المنطق على أماكن النداء يعني أن قناة تُنسى في أحدها.
 */
final class Notifier
{
    public function __construct(
        private readonly EventCatalogue $catalogue,
        private readonly ChannelRegistry $channels,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return list<string> القنوات التي وُضعت في الطابور فعلاً
     */
    public function send(string $eventKey, User|Collection|array $to, array $data = []): array
    {
        $event = $this->catalogue->get($eventKey);

        if ($event === null) {
            return [];
        }

        $queued = [];

        foreach ($this->recipients($to) as $user) {
            foreach ($this->channelsFor($event, $user) as $channel) {
                $this->queue($event, $user, $channel, $data);
                $queued[] = $channel;
            }
        }

        return array_values(array_unique($queued));
    }

    /**
     * القنوات التي يجب أن تصل هذا المستخدم في هذا الحدث.
     *
     * @return list<string>
     */
    public function channelsFor(NotificationEvent $event, User $user): array
    {
        if (! $this->catalogue->has($event->key)) {
            return [];
        }

        $templates = NotificationTemplate::where('event', $event->key)->get()->keyBy('channel');
        $overrides = NotificationPreference::where('user_id', $user->getKey())
            ->where('event', $event->key)->get()->keyBy('channel');

        $channels = [];

        foreach ($event->channels as $key) {
            $channel = $this->channels->get($key);

            if ($channel === null || ! $channel->isReady()) {
                continue;
            }

            $template = $templates->get($key);

            // القالب المطفأ من اللوحة يُسكِت القناة لكل المستخدمين
            $enabled = $template === null ? $event->isOnByDefault($key) : (bool) $template->is_enabled;

            // ثم تفضيل المستخدم — إلا في الأحداث الأمنية، فهي لا تُطفأ
            if ($this->respectsPreferences() && ! $event->isMandatory() && $overrides->has($key)) {
                $enabled = (bool) $overrides->get($key)->is_enabled;
            }

            if ($enabled) {
                $channels[] = $key;
            }
        }

        return $channels;
    }

    /** @param  array<string, mixed>  $data */
    private function queue(NotificationEvent $event, User $user, string $channelKey, array $data): void
    {
        $delay = $this->quietDelayFor($event, $user);

        $log = NotificationLog::create([
            'event' => $event->key,
            'channel' => $channelKey,
            'user_id' => $user->getKey(),
            'status' => 'queued',
        ]);

        $job = new SendNotification($event->key, $channelKey, (int) $user->getKey(), $data, (int) $log->getKey());

        // ساعات الهدوء تؤجّل ولا تُلغي: رسالة لم تصل خير منها في الثانية فجراً
        $delay === null ? dispatch($job) : dispatch($job)->delay($delay);
    }

    /**
     * ساعات الهدوء: لا رسالة بين ساعتين يضبطهما المشترك.
     *
     * الحدث الأمني والإشعار داخل الموقع خارج القاعدة: الأول عاجل
     * بطبيعته، والثاني لا يرنّ ولا يوقظ أحداً.
     */
    private function quietDelayFor(NotificationEvent $event, User $user): ?Carbon
    {
        if (! (bool) setting('notifications.quiet_hours', true) || $event->isMandatory()) {
            return null;
        }

        $timezone = (string) ($user->timezone ?: tenant('timezone') ?: config('app.timezone'));
        $now = now()->setTimezone($timezone);
        $from = (int) setting('notifications.quiet_from', 22);
        $to = (int) setting('notifications.quiet_to', 8);

        if ($from === $to) {
            return null;
        }

        $hour = (int) $now->format('G');

        // النافذة قد تعبر منتصف الليل (٢٢ ← ٨)، فالمقارنة تختلف باتجاهها
        $inside = $from < $to
            ? ($hour >= $from && $hour < $to)
            : ($hour >= $from || $hour < $to);

        if (! $inside) {
            return null;
        }

        $wake = $now->copy()->setTime($to, 0);

        if ($wake->lte($now)) {
            $wake->addDay();
        }

        return $wake->setTimezone(config('app.timezone'));
    }

    private function respectsPreferences(): bool
    {
        return (bool) setting('notifications.user_preferences', true);
    }

    /** @return iterable<User> */
    private function recipients(User|Collection|array $to): iterable
    {
        if ($to instanceof User) {
            return [$to];
        }

        return $to instanceof Collection ? $to->all() : $to;
    }
}
