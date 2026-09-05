<?php

declare(strict_types=1);

namespace App\Modules\Whatsapp;

use App\Core\Billing\PlatformSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * بوّابة واتساب عبر Evolution API — خادمٌ تملكه المنصّة.
 *
 * ## لماذا لا Cloud API من ميتا وحدها
 *
 * ميتا تشترط حساب أعمال موثّقاً وقوالبَ معتمدةً مسبقاً، ولا تسمح
 * برسالة حرّة إلا داخل نافذة ٢٤ ساعة. وذلك بابٌ يقف عنده كل مدرّس
 * مصريّ: توثيقٌ يأخذ أسابيع، وقالبٌ يُراجَع، ورقمٌ لا يستعمله هو.
 *
 * وEvolution يربط رقم المدرّس نفسه — الرقم الذي يعرفه طلابه —
 * بمسح رمزٍ من هاتفه، فيرسل منه بلا توثيق ولا قوالب.
 *
 * ## والمفتاح العام لا يخرج إلى المشترك
 *
 * الخادم يُدار بمفتاحٍ عام يُنشئ النُّسَخ ويحذفها. ولو وصل مشترِكاً
 * لحذف نُسَخ غيره. فهو في إعدادات المنصّة وحدها، ولكل مشترك مفتاح
 * نسخته يُنشأ معها ويُحفظ في قاعدته.
 *
 * ## ونسخةٌ لكل مشترك لا نسخةٌ للمنصّة
 *
 * الرسائل تخرج باسم المدرّس ورقمه، لا باسمنا. ولو خرجت من رقمٍ
 * واحد لبدت رسائل مدرّسين مختلفين من مصدرٍ واحد — وهو ما يجعل
 * واتساب يحظر الرقم أصلاً.
 */
final class EvolutionApi
{
    public function __construct(private readonly PlatformSettings $platform) {}

    public function configured(): bool
    {
        return filled($this->server()) && filled($this->globalKey());
    }

    /** اسم النسخة: مشتقٌّ من معرّف المشترك فلا يتكرّر ولا يُخمَّن */
    public function instanceNameFor(string $tenantId, string $slug): string
    {
        return Str::slug($slug).'-'.substr(sha1($tenantId), 0, 10);
    }

    /**
     * ينشئ نسخةً ويعيد مفتاحها ورمز الاقتران.
     *
     * @return array{instance:string, token:?string, qr:?string, pairing:?string}
     *
     * @throws RuntimeException
     */
    public function createInstance(string $instance): array
    {
        $response = $this->request()->post($this->server().'/instance/create', [
            'instanceName' => $instance,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->message($response->status(), (string) $response->body()));
        }

        return [
            'instance' => $instance,
            'token' => $response->json('hash.apikey') ?? $response->json('hash') ?? null,
            'qr' => $response->json('qrcode.base64'),
            'pairing' => $response->json('qrcode.pairingCode'),
        ];
    }

    /**
     * رمز اقترانٍ جديد لنسخةٍ قائمة.
     *
     * الرمز ينتهي بعد دقيقةٍ تقريباً، فمن فتح الشاشة وتأخّر يحتاج
     * غيره — ولا يُنشأ له نسخةٌ ثانية.
     *
     * @return array{qr:?string, pairing:?string}
     */
    public function connect(string $instance): array
    {
        $response = $this->request()->get($this->server().'/instance/connect/'.$instance);

        if ($response->failed()) {
            throw new RuntimeException($this->message($response->status(), (string) $response->body()));
        }

        return [
            'qr' => $response->json('base64') ?? $response->json('qrcode.base64'),
            'pairing' => $response->json('pairingCode') ?? $response->json('qrcode.pairingCode'),
        ];
    }

    /** open | connecting | close | unknown */
    public function state(string $instance): string
    {
        $response = $this->request()->get($this->server().'/instance/connectionState/'.$instance);

        if ($response->failed()) {
            return 'unknown';
        }

        return (string) ($response->json('instance.state') ?? $response->json('state') ?? 'unknown');
    }

    /** الرقم المرتبط — يُعرَض للمشترك ليتأكّد أنه ربط الصحيح */
    public function number(string $instance): ?string
    {
        $response = $this->request()->get($this->server().'/instance/fetchInstances', ['instanceName' => $instance]);

        if ($response->failed()) {
            return null;
        }

        $rows = (array) $response->json();
        $first = $rows[0] ?? $rows;

        $owner = $first['instance']['owner'] ?? $first['ownerJid'] ?? $first['owner'] ?? null;

        return is_string($owner) ? Str::before($owner, '@') : null;
    }

    /** @throws RuntimeException */
    public function sendText(string $instance, string $token, string $number, string $text): ?string
    {
        $response = $this->request($token)->post($this->server().'/message/sendText/'.$instance, [
            'number' => $number,
            'text' => $text,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->message($response->status(), (string) $response->body()));
        }

        return $response->json('key.id');
    }

    public function logout(string $instance): void
    {
        $this->request()->delete($this->server().'/instance/logout/'.$instance);
    }

    public function delete(string $instance): void
    {
        $this->logout($instance);
        $this->request()->delete($this->server().'/instance/delete/'.$instance);
    }

    public function server(): string
    {
        return rtrim((string) $this->platform->get('whatsapp_server_url', ''), '/ ');
    }

    private function globalKey(): string
    {
        return (string) $this->platform->get('whatsapp_global_key', '');
    }

    /** المفتاح العام ما لم يُعطَ مفتاح النسخة */
    private function request(?string $token = null): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException(__('لم تُضبط بوّابة واتساب في إعدادات المنصّة.'));
        }

        return Http::withHeaders(['apikey' => $token ?: $this->globalKey()])
            ->timeout(25)
            ->acceptJson();
    }

    /**
     * الأخطاء تُترجَم إلى ما يفهمه المشترك.
     *
     * ولا يُعاد نصّ الخادم كما هو: قد يحمل تفصيلاً عن بوّابتنا نحن
     * أو أسماء نُسَخ مشتركين آخرين.
     */
    private function message(int $status, string $body): string
    {
        return match (true) {
            $status === 401 || $status === 403 => __('مفتاح بوّابة واتساب غير صالح.'),
            $status === 404 => __('لم تُعثر النسخة على الخادم — أعد الربط.'),
            $status === 409 => __('هذا الاسم مستعمل على الخادم بالفعل.'),
            $status >= 500 => __('خادم واتساب لا يستجيب الآن.'),
            default => __('تعذّر إتمام الطلب مع خادم واتساب.'),
        };
    }
}
