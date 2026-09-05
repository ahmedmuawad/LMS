<?php

use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        /*
         | الواجهة البرمجية خارج حراسة CSRF.
         |
         | CSRF يحمي متصفّحاً يحمل كوكيّاً؛ والتكامل يعمل من خادمٍ
         | آخر بلا كوكيّات، وجهازُ البصمة لا يعرف ما هو الرمز أصلاً.
         | وحراستهما بالمفتاح في `Authorization` — وهي أقوى، لأن
         | المفتاح لا يُرسَل تلقائياً كما يُرسَل الكوكي.
         */
        $middleware->validateCsrfTokens(except: [
            'api/v1/*',
            '*/api/v1/*',
        ]);

        /*
         | حارس الميزات باسمٍ مختصر.
         |
         | القفل في القائمة الجانبية بصريّ لا أمنيّ: المسار يُفتح
         | بكتابته. فما كان ميزةً في الباقة يُحرَس في مساره أيضاً،
         | و`feature:h5p` أقصر من ذكر الصنف في كل مجموعة.
         */
        $middleware->alias(['feature' => EnsureFeature::class]);

        // ApplyTenantTheme يُطبَّق داخل routes/tenant.php لأنه يجب أن
        // يعمل بعد InitializeTenancyByDomain لا قبلها.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         | عنوانٌ لا منصّة عليه: ٤٠٤ لا ٥٠٠.
         |
         | كان أيّ نطاقٍ فرعيّ غير مسجّل يرمي استثناءً غير مُعالَج،
         | فيرى من أخطأ حرفاً في عنوان مدرّسه «خطأ في الخادم» ويظنّ
         | المنصّة معطّلة. وهو ليس خطأً عندنا، فلا يُكتب في السجلّ
         | كذلك — كان كل زائرٍ مخطئ يكتب أثراً كاملاً.
         */
        $exceptions->dontReport(TenantCouldNotBeIdentifiedOnDomainException::class);

        $exceptions->render(function (
            TenantCouldNotBeIdentifiedOnDomainException $e,
            Request $request,
        ) {
            $home = rtrim((string) config('app.url'), '/');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('لا توجد منصّة على هذا العنوان.'),
                    'home' => $home,
                ], 404);
            }

            return response()->view('errors.tenant-not-found', [
                'host' => $request->getHost(),
                'home' => $home,
            ], 404);
        });
    })->create();
