<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Models\User;
use App\Modules\Lms\Models\UserDevice;
use Illuminate\Http\Request;

/**
 * حدّ الأجهزة لكل حساب.
 *
 * `device_limit` كان مفتاحاً في الباقات بلا سطر كود، والحساب الواحد
 * يُتداول بين عشرة فيدفع واحدٌ ويشاهد عشرة.
 *
 * ## البصمة
 *
 * لا توجد بصمة متصفّح صادقة تماماً: المستخدم قد يمسح كوكيّاته أو
 * يفتح نافذةً خاصة فيبدو جهازاً جديداً. فنجمع علامتين:
 *
 *   · مُعرّفٌ دائم في كوكي طويل العمر — دقيق ما دام قائماً
 *   · وترويسات الجهاز — تُبقي الجهاز معروفاً بعد مسح الكوكيّات
 *
 * والنتيجة ليست يقيناً، لكنها تكفي لغرضها: من يشارك حسابه مع عشرة
 * يُوقَف، ومن يبدّل متصفّحه أحياناً لا يُزعَج.
 *
 * ## ولا يُطرَد أحد تلقائياً
 *
 * الجهاز الجديد فوق الحدّ يُرفض، ولا يُفصل جهازٌ قائم ليدخل مكانه:
 * من يذاكر الآن لا يُقطع عليه درسه لأن صاحب الحساب فتح هاتفه.
 */
final class DeviceGuard
{
    private const COOKIE = 'device_id';

    private const COOKIE_DAYS = 400;

    /**
     * يسجّل الجهاز الحالي، ويقول هل هو مسموح.
     *
     * لا يرمي ولا يُخرج: القرار لمن استدعى — فشاشة الدخول تعرض
     * رسالةً، والمِدلوير يقطع الجلسة، وكلاهما يحتاج الجواب لا
     * الاستثناء.
     */
    public function register(Request $request, User $user, ?int $limit): DeviceCheck
    {
        $fingerprint = $this->fingerprint($request);

        $device = UserDevice::firstOrNew([
            'user_id' => $user->getKey(),
            'fingerprint' => $fingerprint,
        ]);

        $known = $device->exists;

        // الحدّ لا يمسّ من يعرفه النظام أصلاً: هو لا يزيد العدد
        if (! $known && $limit !== null && $limit > 0) {
            $used = UserDevice::where('user_id', $user->getKey())->where('is_trusted', true)->count();

            if ($used >= $limit) {
                return new DeviceCheck(false, $used, $limit, $fingerprint);
            }
        }

        $device->forceFill([
            'label' => $this->label($request),
            'last_ip' => $request->ip(),
            'first_seen_at' => $device->first_seen_at ?? now(),
            'last_seen_at' => now(),
            'is_trusted' => true,
        ])->save();

        return new DeviceCheck(true, 0, $limit, $fingerprint);
    }

    /** الكوكي يُثبَّت في الردّ — يُستدعى بعد نجاح الدخول */
    public function rememberCookie(string $fingerprint): void
    {
        cookie()->queue(cookie(
            self::COOKIE,
            $this->rawId($fingerprint),
            60 * 24 * self::COOKIE_DAYS,
            httpOnly: true,
        ));
    }

    private function fingerprint(Request $request): string
    {
        $id = (string) $request->cookie(self::COOKIE);

        if ($id === '') {
            $id = bin2hex(random_bytes(16));
            cookie()->queue(cookie(self::COOKIE, $id, 60 * 24 * self::COOKIE_DAYS, httpOnly: true));
        }

        /*
         | الترويسات تدخل التجزئة مع المعرّف.
         |
         | فمسحُ الكوكيّات وحده لا يجعل الجهاز جديداً — لكنّ تبديل
         | المتصفّح يجعله كذلك، وهو تبديلُ جهازٍ فعلاً من ناحية
         | المشاركة.
         */
        return hash('sha256', implode('|', [
            $id,
            $request->userAgent() ?? '',
            $request->header('Accept-Language', ''),
        ]));
    }

    private function rawId(string $fingerprint): string
    {
        return mb_substr($fingerprint, 0, 32);
    }

    /** وصفٌ يقرؤه صاحب الحساب ليعرف أيّ جهاز يفكّه */
    private function label(Request $request): string
    {
        $agent = (string) $request->userAgent();

        $browser = match (true) {
            str_contains($agent, 'Edg') => 'Edge',
            str_contains($agent, 'OPR') => 'Opera',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Safari') => 'Safari',
            default => __('متصفّح'),
        };

        $os = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => __('نظام'),
        };

        return $browser.' · '.$os;
    }
}
