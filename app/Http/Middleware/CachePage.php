<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Support\PageCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * كاش الصفحة الكاملة — للزائر وحده.
 *
 * ## إعدادٌ كان يَعِد ولا يفعل
 *
 * «صفحات تُخزَّن مؤقتاً» و«مدة كاش الصفحة» حقلان في شاشة الأداء منذ
 * البداية ولا سطرَ يقرؤهما. فيظنّ المشترك أن رئيسيّته مخزَّنة، وكلُّ
 * زائرٍ يبني صفحته من الصفر: عشرون استعلاماً لصفحةٍ لم تتغيّر منذ
 * أسبوع.
 *
 * ## وللزائر وحده
 *
 * صفحةٌ فيها «أهلاً يا أحمد» تُخزَّن فيراها كلُّ زائرٍ بعده باسم
 * أحمد — وهذا تسريبُ هويّة لا بطءُ صفحة. فالمسجَّل يُخدَم دائماً
 * من الحيّ.
 *
 * ## ولا يُخزَّن إلا ٢٠٠ من GET
 *
 * تخزينُ صفحة خطأٍ يجعل عطلاً عابراً يدوم ساعة. والـPOST يُغيّر
 * حالةً بطبعه، فلا معنى لحفظ جوابه.
 */
final class CachePage
{
    /** ما لا يُخزَّن مهما كان الإعداد: لوحاتٌ ومساراتُ عمل */
    private const NEVER = [
        'admin', 'admin/*', 'super', 'super/*', 'me', 'my-*',
        'cart', 'checkout', 'checkout/*', 'orders/*', 'login', 'logout',
        'register', 'api/*', 'live/*', 'checkin/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->cacheable($request)) {
            return $next($request);
        }

        $key = $this->key($request);
        $minutes = max(1, (int) setting('performance.page_cache_minutes', 60));

        $cached = Cache::get($key);

        if (is_string($cached)) {
            return response($cached)
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('X-Page-Cache', 'hit');
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200 && $this->isHtml($response)) {
            Cache::put($key, $response->getContent(), now()->addMinutes($minutes));
        }

        return $response->headers->has('X-Page-Cache')
            ? $response
            : $response->header('X-Page-Cache', 'miss');
    }

    private function cacheable(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->user() !== null || tenant() === null) {
            return false;
        }

        /*
         | ومن يحمل جلسةً بها رسالة لا تُخزَّن صفحته.
         |
         | «تمّ حفظ طلبك» تُخزَّن فتظهر لكل زائرٍ بعده — ورسالةٌ عن
         | فعلٍ لم يفعله تُربكه.
         */
        if ($request->hasSession() && $request->session()->has('status')) {
            return false;
        }

        if ($request->is(...self::NEVER)) {
            return false;
        }

        return $this->pageKind($request) !== null;
    }

    /**
     * أيّ نوعٍ من الصفحات هذه — إن كان المشترك اختار تخزينه.
     *
     * والمطابقة بالمسار لا بالراوت: أسماء الراوتات تتغيّر، والمسار
     * هو ما يراه المشترك في إعداده.
     */
    private function pageKind(Request $request): ?string
    {
        $enabled = (array) (setting('performance.cached_pages') ?? []);

        $kind = match (true) {
            $request->is('/', 'en') => 'home',
            $request->is('courses', 'en/courses') => 'course_list',
            $request->is('courses/*', 'en/courses/*') => 'course_page',
            $request->is('blog', 'blog/*', 'en/blog', 'en/blog/*') => 'blog',
            $request->is('p/*', 'en/p/*') => 'pages',
            default => null,
        };

        return $kind !== null && in_array($kind, $enabled, true) ? $kind : null;
    }

    /**
     * المفتاح يحمل ما يغيّر الصفحة.
     *
     * اللغة والوضع الداكن والاستعلام كلُّها تُنتج صفحاتٍ مختلفة؛
     * ومفتاحٌ يهملها يخدم النسخة العربية لمن طلب الإنجليزية.
     */
    private function key(Request $request): string
    {
        return 'page:'.sha1(implode('|', [
            (string) tenant('id'),

            /*
             | رقم النسخة داخل المفتاح.
             |
             | فنشرُ كورسٍ يرفعه، فتصير كلُّ المفاتيح القديمة غير
             | مطلوبةٍ أبداً وتموت وحدها بانتهاء مدّتها. وبلا هذا
             | يرى المشترك القديم ساعةً بعد حفظه، فيظنّ الحفظ فشل.
             */
            PageCache::version(),

            app()->getLocale(),
            $request->getPathInfo(),
            $request->getQueryString() ?? '',
        ]));
    }

    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
