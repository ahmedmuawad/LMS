<?php

declare(strict_types=1);

namespace App\Core\Notifications\Channels;

use App\Core\Notifications\Delivery;
use App\Modules\Whatsapp\EvolutionApi;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * واتساب عبر Cloud API من ميتا.
 *
 * يفوق البريد في الوصول لدى أولياء الأمور في مصر بفارق لا يُقارَن.
 * وميتا لا تسمح برسالة حرّة إلا داخل نافذة ٢٤ ساعة من ردّ العميل،
 * فالافتراضي هنا قالب معتمد مسبقاً لا نصّ نكتبه لحظة الإرسال.
 */
final class WhatsAppChannel implements Channel
{
    private const ENDPOINT = 'https://graph.facebook.com/v21.0';

    public function key(): string
    {
        return 'whatsapp';
    }

    public function label(): string
    {
        return __('واتساب');
    }

    /**
     * جاهزةٌ ببوّابة المنصّة أو بحساب ميتا.
     *
     * وبوّابة المنصّة أولاً: يربط المشترك رقمه بمسح رمزٍ في دقيقة،
     * بلا توثيق حساب أعمال ولا قوالبَ تُراجَع أسبوعاً.
     */
    public function isReady(): bool
    {
        if (! setting('notifications.whatsapp_enabled', false)) {
            return false;
        }

        return $this->viaGateway() || (filled(setting('notifications.whatsapp_phone_id'))
            && filled(setting('notifications.whatsapp_token')));
    }

    /** هل لهذا المشترك نسخةٌ موصولة على بوّابة المنصّة؟ */
    private function viaGateway(): bool
    {
        return filled(tenant()?->wa_instance) && app(EvolutionApi::class)->configured();
    }

    public function destinationFor(Delivery $delivery): ?string
    {
        $phone = $this->normalise((string) $delivery->user->phone);

        return $phone === '' ? null : $phone;
    }

    public function send(Delivery $delivery): ?string
    {
        $to = $this->destinationFor($delivery);

        if ($to === null) {
            return null;
        }

        /*
         | بوّابة المنصّة ترسل نصّاً حرّاً بلا قوالب.
         |
         | فهي رقم المشترك نفسه، والرسالة تخرج منه كما لو كتبها —
         | ولا شرط نافذة الأربع والعشرين ساعة ولا قالبٌ معتمد.
         */
        if ($this->viaGateway()) {
            return app(EvolutionApi::class)->sendText(
                (string) tenant()->wa_instance,
                (string) tenant()->wa_token,
                $to,
                $delivery->body,
            );
        }

        $response = Http::withToken((string) setting('notifications.whatsapp_token'))
            ->timeout(15)
            ->post(self::ENDPOINT.'/'.setting('notifications.whatsapp_phone_id').'/messages', $this->payload($delivery, $to));

        if ($response->failed()) {
            throw new RuntimeException('WhatsApp: '.$response->json('error.message', $response->status()));
        }

        return $response->json('messages.0.id');
    }

    /** @return array<string, mixed> */
    private function payload(Delivery $delivery, string $to): array
    {
        if ($delivery->providerTemplate === null) {
            // نصّ حرّ: يمرّ فقط داخل نافذة الردّ، وميتا ترفضه خارجها
            return [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $delivery->body],
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $delivery->providerTemplate,
                'language' => ['code' => $delivery->locale === 'ar' ? 'ar' : 'en'],
                'components' => [[
                    'type' => 'body',
                    'parameters' => array_map(
                        fn (string $value): array => ['type' => 'text', 'text' => $value],
                        $this->parameters($delivery),
                    ),
                ]],
            ],
        ];
    }

    /**
     * متغيّرات القالب بترتيب إعلانها في الحدث.
     *
     * ميتا ترقّم المعاملات ولا تسمّيها، فالترتيب هو العقد: تغييره
     * في الكتالوج يقلب الرسالة رأساً على عقب.
     *
     * @return list<string>
     */
    private function parameters(Delivery $delivery): array
    {
        $values = [];

        foreach ($delivery->event->variables as $variable) {
            $value = $delivery->data[$variable] ?? null;

            if (is_scalar($value) && (string) $value !== '') {
                $values[] = (string) $value;
            }
        }

        return array_slice($values, 0, 10);
    }

    /** رقم مصري محلي يُحوَّل إلى صيغة دولية بلا +، كما تطلب ميتا. */
    private function normalise(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return (string) setting('general.country_code', '20').substr($digits, 1);
        }

        return $digits;
    }
}
