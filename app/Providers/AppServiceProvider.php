<?php

namespace App\Providers;

use App\Core\Access\Ability;
use App\Core\Access\Roles;
use App\Core\Access\Scope;
use App\Core\Admin\Navigation;
use App\Core\Modules\ModuleState;
use App\Core\Settings\SettingsRepository;
use App\Core\Theming\ThemeManager;
use App\Models\User;
use App\Modules\Commerce\Observers\CourseObserver;
use App\Modules\Lms\Models\Course;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        // كذلك حالة الموديولات: تُقرأ مرة في الطلب وتُفرغ مع تبديل السياق.
        $this->app->scoped(ModuleState::class);

        // الحكم على الصلاحيات مصدره واحد، والنطاق يُشتقّ منه
        $this->app->singleton(Roles::class);
        $this->app->scoped(Scope::class);

        /*
         | القائمة تُبنى من حالة المشترك الحالي، ولا تُحفظ في الحاوية:
         | نسخة مشتركة تحمل قائمة موديولات قديمة بعد تبديل السياق أو
         | تغيير النمط، فتعرض قوائم لا تخصّ المشترك المعروض.
         */
        $this->app->bind(Navigation::class, fn (): Navigation => new Navigation(tenant()));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         | كل صلاحية تُسجَّل بوابةً في Laravel، فتعمل `@can` في القوالب
         | و`authorize()` في المتحكّمات بلا طبقة موازية نكتبها بأنفسنا.
         |
         | الحكم كلّه يمرّ عبر Roles: مصدر واحد يمنع أن تُسدّ ثغرة في
         | مكان وتبقى مفتوحة في مكانين.
         */
        foreach (Ability::all() as $ability) {
            Gate::define($ability, fn (?User $user): bool => app(Roles::class)->allows($user, $ability));
        }

        // تبديل سياق المشترك يُبطل أي حالة محلية محمّلة،
        // وإلا قرأ المشترك التالي إعدادات السابق.
        Event::listen([TenancyInitialized::class, TenancyEnded::class], function (): void {
            app(SettingsRepository::class)->flush();
        });

        /*
         | المنتج يتبع الكورس تلقائياً. لو تُرك للمشترك لنسِيَه،
         | فيصير الكورس منشوراً ولا يُشترى — وهو أسوأ عطل: كل شيء
         | يبدو سليماً ولا يصل بيع واحد.
         */
        Course::observe(CourseObserver::class);
    }
}
