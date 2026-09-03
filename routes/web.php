<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 | المسارات المركزية — موقعنا نحن (التسويق، التسجيل، لوحة الإدارة العليا).
 |
 | مقيّدة بالنطاقات المركزية حتى لا تتصادم مع مسارات المشتركين،
 | إذ يتشارك الاثنان نفس المسار "/" على نطاقات مختلفة.
 |
 | ADR-003: العربية بلا بادئة، والإنجليزية تحت /en/.
 */

$central = function (): void {
    Route::view('/', 'welcome')->name('home');

    // مرجع نظام التصميم — أداة للفريق، تُقيَّد بغير الإنتاج
    if (! app()->isProduction()) {
        Route::view('/design-system', 'design-system')->name('design-system');
    }
};

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () use ($central): void {
        $central();

        foreach (config('locales.prefixed') as $prefix) {
            Route::prefix($prefix)->name($prefix.'.')->group($central);
        }
    });
}
