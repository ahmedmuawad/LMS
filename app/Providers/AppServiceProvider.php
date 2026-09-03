<?php

namespace App\Providers;

use App\Core\Theming\ThemeManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // مفردة إلزاماً: تحتفظ بمسارات العرض الأصلية،
        // ونسخة جديدة لكل طلب تلتقط مسارات ملوّثة كأنها الأصل.
        $this->app->singleton(ThemeManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
