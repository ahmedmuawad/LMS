<?php

namespace App\Providers;

use App\Core\Settings\SettingsRepository;
use App\Core\Theming\ThemeManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;

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

        // مفردة لكل طلب: تحمل الكاش المحلي للإعدادات.
        // tenancy تُفرغها عند تبديل سياق المشترك.
        $this->app->scoped(SettingsRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // تبديل سياق المشترك يُبطل أي حالة محلية محمّلة،
        // وإلا قرأ المشترك التالي إعدادات السابق.
        Event::listen([TenancyInitialized::class, TenancyEnded::class], function (): void {
            app(SettingsRepository::class)->flush();
        });
    }
}
