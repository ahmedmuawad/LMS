<x-layouts.onboarding :title="__('طريقة التقديم')" :step="$step" :wizard="$wizard">
    <x-ui.card :title="__('كيف تقدّم محتواك؟')"
               :subtitle="__('يحدّد الحقول واللوحات التي تظهر عند إنشاء كورس.')">
        <form method="POST" action="{{ url('/onboarding/delivery') }}">
            @csrf
            <div class="grid gap-3">
                @foreach($wizard->deliveryModes() as $key => $delivery)
                    <x-ui.radio-card name="delivery" :value="$key"
                                     :icon="['recorded' => '▶', 'live' => '◉', 'blended' => '◈'][$key] ?? '▶'"
                                     :label="$delivery['name'][app()->getLocale()] ?? $delivery['name']['ar']"
                                     :hint="[
                                         'recorded' => __('دروس مسجّلة يشاهدها الطالب متى شاء.'),
                                         'live'     => __('حصص مباشرة عبر Zoom أو Meet أو BigBlueButton أو Jitsi.'),
                                         'blended'  => __('الاثنان معاً: حصة مباشرة تُسجَّل وتُضاف للمكتبة.'),
                                     ][$key] ?? ''"
                                     :checked="$tenant->delivery_mode === $key" />
                @endforeach
            </div>

            @error('delivery')<p class="text-xs text-danger mt-2">{{ $message }}</p>@enderror

            <div class="flex justify-between mt-6">
                <x-ui.button variant="ghost" :href="url('/onboarding/mode')">{{ __('رجوع') }}</x-ui.button>
                <x-ui.button type="submit" size="lg">{{ __('التالي') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.onboarding>
