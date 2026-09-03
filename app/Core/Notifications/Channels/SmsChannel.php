<?php

declare(strict_types=1);

namespace App\Core\Notifications\Channels;

use App\Core\Notifications\Delivery;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * الرسائل النصية.
 *
 * أغلى القنوات وأقصرها، فتُحجز للعاجل: كود تحقق أو غياب ابن.
 * المزوّدون هنا هم من يخدم مصر والخليج فعلاً.
 */
final class SmsChannel implements Channel
{
    public function key(): string
    {
        return 'sms';
    }

    public function label(): string
    {
        return __('رسالة نصية');
    }

    public function isReady(): bool
    {
        return (string) setting('notifications.sms_provider', 'none') !== 'none'
            && filled(setting('notifications.sms_key'));
    }

    public function destinationFor(Delivery $delivery): ?string
    {
        return filled($delivery->user->phone) ? (string) $delivery->user->phone : null;
    }

    public function send(Delivery $delivery): ?string
    {
        $to = $this->destinationFor($delivery);

        if ($to === null) {
            return null;
        }

        // الرسالة تُقصّ عند ١٦٠ محرفاً: ما بعدها يُحاسَب رسالةً ثانية
        $text = mb_substr(trim($delivery->subject."\n".$delivery->body), 0, 160);

        return match ((string) setting('notifications.sms_provider')) {
            'twilio' => $this->twilio($to, $text),
            'unifonic' => $this->unifonic($to, $text),
            'vodafone', 'msegat' => $this->generic($to, $text),
            default => throw new RuntimeException(__('مزوّد رسائل غير معروف.')),
        };
    }

    private function twilio(string $to, string $text): ?string
    {
        $response = Http::asForm()
            ->withBasicAuth((string) setting('notifications.sms_key'), (string) setting('notifications.sms_secret'))
            ->timeout(15)
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.setting('notifications.sms_key').'/Messages.json', [
                'To' => $to,
                'From' => (string) setting('notifications.sms_sender'),
                'Body' => $text,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('SMS: '.$response->json('message', $response->status()));
        }

        return $response->json('sid');
    }

    private function unifonic(string $to, string $text): ?string
    {
        $response = Http::asForm()->timeout(15)->post('https://el.cloud.unifonic.com/rest/SMS/messages', [
            'AppSid' => (string) setting('notifications.sms_key'),
            'SenderID' => (string) setting('notifications.sms_sender'),
            'Recipient' => $to,
            'Body' => $text,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('SMS: '.$response->json('message', $response->status()));
        }

        return $response->json('data.MessageID');
    }

    /** مزوّد محلي بنقطة نهاية تُضبط من الإعدادات. */
    private function generic(string $to, string $text): ?string
    {
        $endpoint = (string) setting('integrations.sms_endpoint', '');

        if ($endpoint === '') {
            throw new RuntimeException(__('لم تُضبط نقطة نهاية مزوّد الرسائل.'));
        }

        $response = Http::timeout(15)->post($endpoint, [
            'username' => (string) setting('notifications.sms_key'),
            'password' => (string) setting('notifications.sms_secret'),
            'sender' => (string) setting('notifications.sms_sender'),
            'to' => $to,
            'message' => $text,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('SMS: '.$response->status());
        }

        return $response->json('id');
    }
}
