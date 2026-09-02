<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 | ADR-003: نسجّل نفس المسارات مرتين — بلا بادئة (العربية)
 | ومع بادئة اللغة لكل لغة أخرى مفعّلة.
 */
$register = function (): void {
    Route::view('/', 'welcome')->name('home');
    Route::view('/design-system', 'design-system')->name('design-system');
};

$register();

foreach (config('locales.prefixed') as $prefix) {
    Route::prefix($prefix)->name($prefix.'.')->group($register);
}
