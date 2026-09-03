<?php

declare(strict_types=1);

namespace App\Core\Notifications\Channels;

use App\Core\Notifications\Delivery;
use App\Core\Notifications\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

/**
 * إشعارات المتصفّح.
 *
 * القناة الوحيدة التي تصل والمستخدم خارج الموقع بلا كلفة رسالة.
 * الاشتراك المرفوض (410) يُحذف فوراً: الاحتفاظ به يُبطئ كل إرسال
 * لاحق ويستهلك حصّة المزوّد بلا فائدة.
 */
final class WebPushChannel implements Channel
{
    public function key(): string
    {
        return 'web_push';
    }

    public function label(): string
    {
        return __('إشعار المتصفّح');
    }

    public function isReady(): bool
    {
        return (bool) setting('notifications.web_push_enabled', false)
            && filled(setting('notifications.vapid_public'))
            && filled(setting('notifications.vapid_private'));
    }

    public function destinationFor(Delivery $delivery): ?string
    {
        $count = PushSubscription::where('user_id', $delivery->user->getKey())->count();

        return $count > 0 ? $count.' '.__('جهازاً') : null;
    }

    public function send(Delivery $delivery): ?string
    {
        $subscriptions = PushSubscription::where('user_id', $delivery->user->getKey())->get();

        if ($subscriptions->isEmpty()) {
            return null;
        }

        $push = new WebPush(['VAPID' => [
            'subject' => (string) (setting('general.site_url') ?: url('/')),
            'publicKey' => (string) setting('notifications.vapid_public'),
            'privateKey' => (string) setting('notifications.vapid_private'),
        ]]);

        $payload = json_encode([
            'title' => $delivery->subject,
            'body' => mb_substr($delivery->body, 0, 200),
            'url' => $delivery->url() ?? url('/notifications'),
        ], JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $subscription) {
            $push->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                ]),
                $payload,
            );
        }

        $sent = 0;
        $failures = [];

        foreach ($push->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;

                continue;
            }

            // 404/410 = اشتراك ميت: يُحذف ولا يُعاد إليه
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getRequest()->getUri()->__toString())->delete();

                continue;
            }

            $failures[] = $report->getReason();
        }

        if ($sent === 0 && $failures !== []) {
            throw new RuntimeException('WebPush: '.implode(' · ', array_slice($failures, 0, 2)));
        }

        return $sent > 0 ? $sent.' delivered' : null;
    }
}
