<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use Stancl\Tenancy\Controllers\TenantAssetsController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * ملفّات المشترك — بنوعٍ صحيح.
 *
 * ## لماذا نتجاوز متحكّم الحزمة
 *
 * `response()->file()` تخمّن النوع من **محتوى** الملفّ (finfo)، لا من
 * امتداده. وملفّ CSS نصٌّ صِرف، فتخرج ترويسته `text/plain` —
 * والمتصفّح يرفض تطبيق ورقة أنماطٍ بهذا النوع في الوضع الصارم. وكذلك
 * JavaScript.
 *
 * لم يظهر هذا قبل حزم H5P لأن ما رفعه المشتركون كان صوراً، والصورة
 * يعرفها finfo من بايتاتها. أمّا الحزمة فتحمل أنماطها وشيفرتها معها.
 *
 * فالنوع يُؤخذ من الامتداد لما نعرفه، ويُترك للتخمين لما سواه.
 */
final class AssetController extends TenantAssetsController
{
    /** @var array<string, string> */
    private const TYPES = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'mjs' => 'application/javascript',
        'json' => 'application/json',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'html' => 'text/html',
        'htm' => 'text/html',
        'txt' => 'text/plain',
        'xml' => 'application/xml',
        'vtt' => 'text/vtt',
        'm3u8' => 'application/vnd.apple.mpegurl',
    ];

    public function asset($path = null)
    {
        $this->validatePath($path);

        try {
            $response = response()->file(storage_path("app/public/$path"));
        } catch (Throwable) {
            abort(404);
        }

        $type = self::TYPES[mb_strtolower(pathinfo((string) $path, PATHINFO_EXTENSION))] ?? null;

        if ($type !== null && $response instanceof BinaryFileResponse) {
            $response->headers->set('Content-Type', $type);
        }

        /*
         | ولا يُخمَّن النوع في المتصفّح.
         |
         | الملفّ يرفعه مشترك، وملفٌّ يُخمَّن نوعه قد يُنفَّذ كصفحة
         | في نطاقه هو — فتسري الجلسة عليه.
         */
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
