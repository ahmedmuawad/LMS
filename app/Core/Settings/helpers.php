<?php

declare(strict_types=1);

use App\Core\Settings\SettingsRepository;

if (! function_exists('setting')) {
    /**
     * setting()                          → المستودع نفسه
     * setting('lms.passing_percentage')  → القيمة
     */
    function setting(?string $path = null, mixed $default = null): mixed
    {
        $repository = app(SettingsRepository::class);

        return $path === null ? $repository : $repository->get($path, $default);
    }
}
