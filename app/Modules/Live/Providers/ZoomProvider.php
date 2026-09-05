<?php

declare(strict_types=1);

namespace App\Modules\Live\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * إنشاء اجتماع على Zoom.
 *
 * ## Server-to-Server OAuth لا تطبيق مستخدم
 *
 * تطبيق Zoom العادي يطلب من كل مدرّس أن يوافق على شاشة أذونات ثم
 * يعود إلينا برمز — وذلك بابٌ يقف عنده أكثرهم فلا يُكمل. أمّا
 * Server-to-Server فيربطه المشترك مرّةً في إعداداته بثلاثة حقول
 * ينسخها من حسابه، ولا يرى بعدها شاشة موافقة أبداً.
 *
 * ## والرمز يُخبَّأ
 *
 * صلاحيته ساعة، وطلبُ رمزٍ جديد مع كل حصة يُبطئ ويستهلك حدّ
 * الطلبات بلا فائدة. فيُخبَّأ خمساً وخمسين دقيقة — دون الساعة
 * بهامشٍ يكفي لطلبٍ بطيء.
 */
final class ZoomProvider
{
    /**
     * @return array{join_url:string, host_url:?string, external_id:?string}
     *
     * @throws RuntimeException
     */
    public function create(string $topic, ?Carbon $start, ?Carbon $end): array
    {
        $token = $this->token();

        $minutes = $start !== null && $end !== null
            ? max(15, min(1440, (int) $start->diffInMinutes($end)))
            : 60;

        $response = Http::withToken($token)
            ->timeout(20)
            ->post('https://api.zoom.us/v2/users/me/meetings', array_filter([
                'topic' => mb_substr($topic, 0, 200),
                // 2 = مجدول، 1 = فوري. والمجدول يظهر في تقويم المدرّس
                'type' => $start !== null ? 2 : 1,
                'start_time' => $start?->utc()->format('Y-m-d\TH:i:s\Z'),
                'duration' => $minutes,
                'timezone' => 'UTC',
                'settings' => [
                    /*
                     | غرفة الانتظار مطفأة والدخول قبل المضيف مفتوح.
                     |
                     | المدرّس قد يتأخّر دقيقتين، وغرفةُ انتظارٍ تجعل
                     | ثلاثين طالباً يظنّون الرابط مكسوراً. والرابط
                     | نفسه محروسٌ بنافذة الفتح عندنا.
                     */
                    'waiting_room' => false,
                    'join_before_host' => true,
                    'approval_type' => 2,
                ],
            ]));

        if ($response->failed()) {
            throw new RuntimeException($this->message($response->status()));
        }

        $joinUrl = (string) ($response->json('join_url') ?? '');

        if ($joinUrl === '') {
            throw new RuntimeException(__('لم يُعِد Zoom رابط اجتماع.'));
        }

        return [
            'join_url' => $joinUrl,
            'host_url' => $response->json('start_url'),
            'external_id' => (string) ($response->json('id') ?? '') ?: null,
        ];
    }

    public static function configured(): bool
    {
        return filled(setting('live.zoom_account_id'))
            && filled(setting('live.zoom_client_id'))
            && filled(setting('live.zoom_client_secret'));
    }

    /** @throws RuntimeException */
    private function token(): string
    {
        $account = (string) setting('live.zoom_account_id');
        $client = (string) setting('live.zoom_client_id');
        $secret = (string) setting('live.zoom_client_secret');

        if ($account === '' || $client === '' || $secret === '') {
            throw new RuntimeException(__('لم تُربط بيانات Zoom بعد.'));
        }

        // المفتاح فيه بصمة البيانات: تغييرها يُبطل الرمز المخبَّأ فوراً
        $key = 'zoom:token:'.substr(sha1($account.'|'.$client.'|'.$secret), 0, 16);

        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::withBasicAuth($client, $secret)
            ->timeout(20)
            ->asForm()
            ->post('https://zoom.us/oauth/token', [
                'grant_type' => 'account_credentials',
                'account_id' => $account,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->message($response->status()));
        }

        $token = (string) ($response->json('access_token') ?? '');

        if ($token === '') {
            throw new RuntimeException(__('لم يُعِد Zoom رمز دخول.'));
        }

        Cache::put($key, $token, now()->addMinutes(55));

        return $token;
    }

    /**
     * الأخطاء تُترجَم إلى ما يفهمه المشترك.
     *
     * «401 Unauthorized» لا تعني له شيئاً، و«بيانات Zoom غير صحيحة»
     * تعني كل شيء. ولا يُعاد نصّ Zoom كما هو: قد يحمل تفصيلاً عن
     * حسابٍ ليس حسابه.
     */
    private function message(int $status): string
    {
        return match (true) {
            $status === 401 || $status === 400 => __('بيانات Zoom غير صحيحة — راجع معرّف الحساب والمفتاح والسرّ.'),
            $status === 403 => __('حساب Zoom لا يملك صلاحية إنشاء الاجتماعات. أضف الصلاحية meeting:write للتطبيق.'),
            $status === 429 => __('طلبات كثيرة على Zoom الآن. أعد المحاولة بعد قليل.'),
            default => __('تعذّر إنشاء اجتماع Zoom الآن.'),
        };
    }
}
