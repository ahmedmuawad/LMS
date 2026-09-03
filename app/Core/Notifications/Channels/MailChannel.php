<?php

declare(strict_types=1);

namespace App\Core\Notifications\Channels;

use App\Core\Notifications\Delivery;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

final class MailChannel implements Channel
{
    public function key(): string
    {
        return 'mail';
    }

    public function label(): string
    {
        return __('البريد');
    }

    public function isReady(): bool
    {
        // «سجّل فقط» وضع تجربة مقصود لا غياب إعداد
        return (string) setting('notifications.mail_provider', 'smtp') !== ''
            && filled(setting('notifications.from_email'));
    }

    public function destinationFor(Delivery $delivery): ?string
    {
        return filled($delivery->user->email) ? (string) $delivery->user->email : null;
    }

    public function send(Delivery $delivery): ?string
    {
        $to = $this->destinationFor($delivery);

        if ($to === null) {
            return null;
        }

        Mail::send([], [], function (Message $message) use ($delivery, $to): void {
            $message->to($to)
                ->subject($delivery->subject)
                ->from(
                    (string) setting('notifications.from_email'),
                    (string) (setting()->translated('notifications.from_name') ?: setting('general.site_name')),
                )
                ->html(view('mail.notification', ['delivery' => $delivery])->render())
                ->text($delivery->body);

            if (filled(setting('notifications.reply_to'))) {
                $message->replyTo((string) setting('notifications.reply_to'));
            }
        });

        return null;
    }
}
