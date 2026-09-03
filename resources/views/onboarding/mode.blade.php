<x-layouts.onboarding :title="__('نمط المنصة')" :step="$step" :wizard="$wizard">
    <x-ui.card :title="__('ما الذي تبنيه؟')"
               :subtitle="__('اختيارك يحدّد ما تراه في لوحتك — لن تظهر لك قوائم لا تخصّك. يمكن تغييره لاحقاً بلا فقد أي بيانات.')">
        <form method="POST" action="{{ url('/onboarding/mode') }}">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($wizard->modes() as $key => $mode)
                    <x-ui.radio-card name="mode" :value="$key" :icon="$mode['icon']"
                                     :label="$mode['name'][app()->getLocale()] ?? $mode['name']['ar']"
                                     :hint="$mode['summary'][app()->getLocale()] ?? $mode['summary']['ar']"
                                     :checked="$tenant->platform_mode === $key" />
                @endforeach
            </div>

            @error('mode')<p class="text-xs text-danger mt-2">{{ $message }}</p>@enderror

            <div class="mt-5 pt-5 border-t border-line">
                <x-ui.switch name="center_enabled" :checked="$tenant->center_enabled"
                             :label="__('أدير سنتراً تعليمياً أيضاً')"
                             :hint="__('مجموعات وجداول حصص وحضور وأقساط وبوابة أولياء أمور — حتى لو كنت مدرّساً فردياً.')" />
            </div>

            <div class="flex justify-end mt-6">
                <x-ui.button type="submit" size="lg">{{ __('التالي') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.onboarding>
