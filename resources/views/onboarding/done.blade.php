<x-layouts.onboarding :title="__('منصّتك جاهزة')" :step="$step" :wizard="$wizard">
    <x-ui.card>
        <div class="text-center py-6">
            <div class="size-16 rounded-2xl bg-success-subtle text-success grid place-items-center mx-auto mb-4 text-2xl" aria-hidden="true">✓</div>
            <h2 class="text-xl font-bold mb-2">{{ __('منصّتك جاهزة') }}</h2>
            <p class="text-muted text-sm max-w-[46ch] mx-auto">
                {{ __('فعّلنا ما يخصّ نمطك فقط، وضبطنا الإعدادات الافتراضية المناسبة. كل شيء قابل للتغيير من الإعدادات.') }}
            </p>
        </div>

        @php
            $locale    = app()->getLocale();
            $themeName = app(App\Core\Theming\ThemeManager::class)->manifest($tenant->theme)['name'][$locale] ?? $tenant->theme;
            $country   = App\Http\Controllers\Tenant\OnboardingController::countries()[$tenant->country] ?? $tenant->country;
            $currency  = App\Http\Controllers\Tenant\OnboardingController::currencies()[$tenant->currency] ?? $tenant->currency;
        @endphp
        <x-ui.description-list :items="[
            __('نمط المنصة')     => config('platform-modes.modes.'.$tenant->platform_mode.'.name.'.$locale),
            __('طريقة التقديم')  => config('platform-modes.delivery.'.$tenant->delivery_mode.'.name.'.$locale),
            __('إدارة السنتر')   => $tenant->center_enabled ? __('مفعّلة') : __('غير مفعّلة'),
            __('الثيم')          => $themeName,
            __('الدولة')         => $country,
            __('العملة')         => $currency.' · '.$tenant->currency,
            __('نطاقك')          => $tenant->domains->first()?->domain,
        ]" />

        <form method="POST" action="{{ url('/onboarding/finish') }}" class="mt-6">
            @csrf
            <div class="flex flex-wrap gap-2 justify-between">
                <x-ui.button variant="ghost" :href="url('/onboarding/mode')">{{ __('تعديل الاختيارات') }}</x-ui.button>
                <x-ui.button type="submit" size="lg">{{ __('ادخل لوحة التحكم') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.onboarding>
