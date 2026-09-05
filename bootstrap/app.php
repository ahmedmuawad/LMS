<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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

        // ApplyTenantTheme يُطبَّق داخل routes/tenant.php لأنه يجب أن
        // يعمل بعد InitializeTenancyByDomain لا قبلها.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
