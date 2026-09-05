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
            && filled($this->fromAddress());
    }

    /**
     * عنوان المُرسِل — من إعداد المشترك، وإلا فمن إعداد المنصة.
     *
     * كان يشترط `notifications.from_email` وحده، ومشتركٌ جديد لا
     * يملؤه أبداً: ينشئ حسابه ثم يطلب استعادة كلمة مروره فلا يصل
     * شيء — بلا رسالة خطأ ولا سجلّ، لأن القناة تُعدّ «غير جاهزة»
     * فتُتخطّى بصمت.
     *
     * والمنصة تملك بريداً مُرسِلاً صالحاً في `MAIL_FROM_ADDRESS`،
     * فالافتراض إليه أصدق من الصمت. ومن أراد بريده كتبه.
     */
    private function fromAddress(): ?string
    {
        $tenantFrom = setting('notifications.from_email');

        return filled($tenantFrom)
            ? (string) $tenantFrom
            : (config('mail.from.address') ?: null);
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
                    (string) $this->fromAddress(),
                    (string) (setting()->translated('notifications.from_name') ?: site_name()),
                )
                ->html(view('mail.notification', ['delivery' => $delivery])->render())
                ->text($delivery->body);

            /*
             | الردّ يذهب إلى المشترك لا إلينا.
             |
             | حين نُرسل من بريد المنصة (لأن المشترك لم يربط بريده)،
             | يردّ الطالب على الرسالة فتصل صندوقنا نحن — ولا نعرف
             | جواب سؤاله عن حصّته. فالردّ يُوجَّه إلى بريد صاحب
             | المنصة، وهو من يملك الجواب.
             */
            $replyTo = setting('notifications.reply_to') ?: tenant('owner_email');

            if (filled($replyTo)) {
                $message->replyTo((string) $replyTo);
            }
        });

        return null;
    }
}
