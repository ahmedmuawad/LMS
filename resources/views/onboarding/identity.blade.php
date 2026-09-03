<x-layouts.onboarding :title="__('هوية منصّتك')" :step="$step" :wizard="$wizard">
    <form method="POST" action="{{ url('/onboarding/identity') }}" class="flex flex-col gap-4">
        @csrf
        <x-ui.card :title="__('الاسم والوصف')">
            <x-ui.field :label="__('اسم المنصة')" for="name" :required="true" :error="$errors->first('name')">
                <x-ui.input id="name" name="name" :value="old('name', $tenant->name)" :invalid="$errors->has('name')" />
            </x-ui.field>
            <x-ui.field :label="__('وصف مختصر')" for="tagline"
                        :hint="__('يظهر في نتائج البحث وعند مشاركة الرابط.')" :error="$errors->first('tagline')">
                <x-ui.input id="tagline" name="tagline" :value="old('tagline', setting('site.tagline'))"
                            :placeholder="__('تعلّم مهارة جديدة من بيتك')" />
            </x-ui.field>
        </x-ui.card>

        <x-ui.card :title="__('الثيم')" :subtitle="__('يمكنك تغييره وتخصيص ألوانه لاحقاً من إعدادات المظهر.')">
            @php
                $available = $wizard->themesFor($tenant->platform_mode);
                $selected  = old('theme', array_key_exists($tenant->theme, $available) ? $tenant->theme : array_key_first($available));
            @endphp
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($available as $key => $theme)
                    <x-ui.radio-card name="theme" :value="$key" :icon="$theme['icon'] ?? '◐'"
                                     :label="$theme['name'][app()->getLocale()] ?? $theme['name']['ar'] ?? $key"
                                     :hint="__('يدعم :list', ['list' => implode('، ', array_map(fn ($s) => [
                                         'dark' => __('الوضع الداكن'), 'rtl' => __('العربية'), 'page-builder' => __('محرّر الصفحات'),
                                     ][$s] ?? $s, $theme['supports'] ?? []))])"
                                     :checked="$selected === $key" />
                @endforeach
            </div>
            @error('theme')<p class="text-xs text-danger mt-2">{{ $message }}</p>@enderror

            <div class="mt-5 pt-5 border-t border-line">
                <x-ui.field :label="__('اللون الأساسي')" for="primary_color"
                            :hint="__('يولّد النظام بقية الدرجات تلقائياً بتباين مضبوط.')">
                    <div class="flex items-center gap-3">
                        <input type="color" id="primary_color" name="primary_color"
                               value="{{ old('primary_color', setting('appearance.primary_color', '#12707E')) }}"
                               class="size-11 rounded-md border border-line-strong bg-surface cursor-pointer p-1">
                        <span class="text-xs text-subtle font-mono">{{ setting('appearance.primary_color', '#12707E') }}</span>
                    </div>
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="flex justify-between">
            <x-ui.button variant="ghost" :href="url('/onboarding/delivery')">{{ __('رجوع') }}</x-ui.button>
            <x-ui.button type="submit" size="lg">{{ __('التالي') }}</x-ui.button>
        </div>
    </form>
</x-layouts.onboarding>
