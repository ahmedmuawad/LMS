<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pwa;

use App\Core\Theming\BrandPalette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * تطبيق الويب التقدّمي.
 *
 * البيان وعامل الخدمة يُولَّدان لكل مشترك: الاسم والأيقونة واللون
 * تخصّه، وملف ثابت واحد يعني أن كل المشتركين يثبّتون أيقونتنا نحن.
 */
final class PwaController
{
    public function manifest(): JsonResponse
    {
        $name = site_name();
        $short = mb_substr($name, 0, 12);
        $brand = (string) (setting('appearance.brand_color') ?: '#0f766e');

        return response()->json([
            'name' => $name,
            'short_name' => $short,
            'description' => (string) (setting()->translated('general.tagline') ?: $name),
            'start_url' => url('/'),
            'scope' => url('/'),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#ffffff',
            'theme_color' => $brand,
            'lang' => app()->getLocale(),
            'dir' => in_array(app()->getLocale(), ['ar', 'fa', 'ur', 'he'], true) ? 'rtl' : 'ltr',
            'icons' => $this->icons($name, $brand),
            'shortcuts' => $this->shortcuts(),
        ], 200, ['Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * عامل الخدمة يُقدَّم من الجذر لا من /build: نطاقه هو المسار
     * الذي يُقدَّم منه، وملف تحت /build لا يستطيع خدمة الموقع كلّه.
     */
    public function serviceWorker(): Response
    {
        return response()
            ->view('pwa.service-worker', [], 200)
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Service-Worker-Allowed', '/')
            ->header('Cache-Control', 'no-cache');
    }

    public function offline(): Response
    {
        return response()->view('pwa.offline', [], 200);
    }

    /**
     * أيقونة SVG مولَّدة من حرف الاسم ولون العلامة.
     *
     * تجنّبنا رفع صور لكل مشترك: SVG قابل للتحجيم، ويكفي حتى يرفع
     * المشترك شعاره الحقيقي من المظهر.
     *
     * @return list<array<string, string>>
     */
    private function icons(string $name, string $brand): array
    {
        $logo = setting('appearance.logo_path');

        if (filled($logo)) {
            return [
                ['src' => (string) $logo, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => (string) $logo, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ];
        }

        $url = url('/icon.svg');

        return [
            ['src' => $url, 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
            ['src' => $url, 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'maskable'],
        ];
    }

    public function icon(): Response
    {
        $name = site_name();

        // الدرجة المضبوطة لا الخام: الحرف أبيض، ولون فاتح يجعله غير مقروء
        $brand = BrandPalette::fromHex((string) (setting('appearance.brand_color') ?: '#0f766e'))->fill;
        $letter = htmlspecialchars(mb_substr(trim($name), 0, 1), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">
                <rect width="512" height="512" rx="96" fill="{$brand}"/>
                <text x="50%" y="50%" dy="0.35em" text-anchor="middle"
                      font-family="system-ui, sans-serif" font-size="260" font-weight="700" fill="#ffffff">{$letter}</text>
            </svg>
            SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function shortcuts(): array
    {
        $shortcuts = [];

        if (module_enabled('lms')) {
            $shortcuts[] = ['name' => __('كورساتي'), 'url' => url('/my-courses')];
        }

        if (module_enabled('bookings')) {
            $shortcuts[] = ['name' => __('حجوزاتي'), 'url' => url('/my-bookings')];
        }

        $shortcuts[] = ['name' => __('الإشعارات'), 'url' => url('/notifications')];

        return $shortcuts;
    }
}
