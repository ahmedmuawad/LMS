<?php

declare(strict_types=1);

namespace App\Core\Notifications\Channels;

use App\Core\Notifications\Delivery;
use App\Core\Notifications\Models\Notification;

/** صندوق الوارد داخل الموقع — جاهز دائماً ولا يحتاج مزوّداً. */
final class DatabaseChannel implements Channel
{
    public function key(): string
    {
        return 'database';
    }

    public function label(): string
    {
        return __('داخل الموقع');
    }

    public function isReady(): bool
    {
        return true;
    }

    public function destinationFor(Delivery $delivery): ?string
    {
        return (string) $delivery->user->getKey();
    }

    public function send(Delivery $delivery): ?string
    {
        $notification = Notification::create([
            'user_id' => $delivery->user->getKey(),
            'event' => $delivery->event->key,
            'title' => $delivery->subject,
            'body' => mb_substr($delivery->body, 0, 500),
            'url' => $delivery->url(),
            'data' => $delivery->data,
        ]);

        return (string) $notification->getKey();
    }
}
