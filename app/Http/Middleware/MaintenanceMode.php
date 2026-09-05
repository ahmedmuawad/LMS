<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Access\Ability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * وضع الصيانة للمشترك.
 *
 * ## كان إعداداً كاذباً
 *
 * الحقل موجود في الإعدادات منذ البداية — «وضع الصيانة» ورسالته
 * وعناوين IP المسموح لها — ولا شيء يقرؤه. فيُفعّله صاحب المنصّة
 * ظانّاً أنه أغلق موقعه، والموقع مفتوح. وإعدادٌ يكذب أسوأ من إعدادٍ
 * غائب: الغائب يُبحث عن بديله، والكاذب يُطمأنّ إليه.
 *
 * ## ولا يُغلَق على صاحبه
 *
 * من يُغلق موقعه يحتاج أن يدخل ليُصلح ما أغلقه لأجله. فأصحاب
 * اللوحة يمرّون دائماً، ومسارات الدخول تبقى مفتوحة — وإلا أغلق
 * المشترك على نفسه ولا سبيل إلى الفتح إلا منّا.
 *
 * ## و٥٠٣ لا ٢٠٠
 *
 * جوجل يقرأ الرمز: ٥٠٣ تعني «عُد لاحقاً» فيحتفظ بترتيب الصفحة،
 * و٢٠٠ بصفحة صيانة تعني «هذا هو المحتوى» فيفهرسها مكان الأصل.
 */
final class MaintenanceMode
{
    /** ما يبقى مفتوحاً: الدخول والخروج وأصول الصفحة */
    private const ALWAYS_OPEN = [
        'login', 'logout', 'admin/*', 'super/*', 'build/*', 'tenancy/assets/*',
        'forgot-password', 'reset-password/*', 'two-factor', 'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! setting('general.maintenance_mode', false) || $this->exempt($request)) {
            return $next($request);
        }

        return response()->view('errors.maintenance', [
            'message' => setting()->translated('general.maintenance_message')
                ?: __('نُجري تحسيناتٍ سريعة. عُد بعد قليل.'),
        ], 503, ['Retry-After' => '3600']);
    }

    private function exempt(Request $request): bool
    {
        if ($request->is(...self::ALWAYS_OPEN)) {
            return true;
        }

        // صاحب اللوحة يرى موقعه وهو مغلق — وإلا لم يستطع مراجعة ما أصلح
        if ($request->user()?->allows(Ability::SETTINGS_MANAGE)) {
            return true;
        }

        return $this->allowedIp($request);
    }

    /**
     * عناوين مسموحٌ لها — للمدرّس يفحص من بيته أو للمصمّم.
     *
     * وتُقرأ سطراً سطراً لا بفاصلة: من يكتب عنواناً في سطرٍ لا
     * يتذكّر أيّ فاصلٍ طُلب منه.
     */
    private function allowedIp(Request $request): bool
    {
        $raw = (string) (setting('general.maintenance_allowed_ips') ?? '');

        if (trim($raw) === '') {
            return false;
        }

        $allowed = collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn (string $ip): string => trim($ip))
            ->filter()
            ->all();

        return in_array((string) $request->ip(), $allowed, true);
    }
}
