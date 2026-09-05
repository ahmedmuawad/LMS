<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Content\Models\NotFoundLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يُسجّل كل عنوانٍ طُلب فردّ ٤٠٤.
 *
 * ## ولماذا مِدلوير لا مُعالج استثناءات
 *
 * لارافيل لا تُبلّغ عن `NotFoundHttpException` أصلاً — هي في قائمة
 * ما لا يُبلَّغ عنه — فمُعالجٌ يُسجَّل لها لا يُنادى أبداً. جرّبناه
 * فلم يُسجَّل شيء.
 *
 * والمِدلوير يرى الردّ كما خرج، فيلتقط الـ٤٠٤ المكتوبة يدوياً
 * (`abort(404)`) كما يلتقط الاستثناء.
 *
 * ## وبعد إرسال الردّ
 *
 * `terminate` تعمل بعد أن يصل الردّ إلى الزائر، فلا يزيد التسجيل
 * زمنَ صفحةٍ هي أصلاً خطأ.
 */
final class LogNotFound
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($response->getStatusCode() !== 404 || ! $request->isMethod('GET') || tenant() === null) {
            return;
        }

        NotFoundLog::record($request->path(), $request->headers->get('referer'));
    }
}
