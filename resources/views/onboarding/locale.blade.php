@php
    $countries  = App\Http\Controllers\Tenant\OnboardingController::countries();
    $currencies = App\Http\Controllers\Tenant\OnboardingController::currencies();
@endphp
<x-layouts.onboarding :title="__('اللغة والعملة')" :step="$step" :wizard="$wizard">
    <x-ui.card :title="__('أين تبيع وبأي عملة؟')"
               :subtitle="__('تحدّد الضريبة وبوابات الدفع المتاحة وصيغة التواريخ والأرقام.')">
        <form method="POST" action="{{ url('/onboarding/locale') }}">
            @csrf
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-ui.field :label="__('الدولة')" for="country" :required="true" :error="$errors->first('country')">
                    <x-ui.select id="country" name="country">
                        @foreach($countries as $code => $name)
                            <option value="{{ $code }}" @selected(old('country', $tenant->country) === $code)>{{ $name }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field :label="__('العملة')" for="currency" :required="true" :error="$errors->first('currency')">
                    <x-ui.select id="currency" name="currency">
                        @foreach($currencies as $code => $name)
                            <option value="{{ $code }}" @selected(old('currency', $tenant->currency) === $code)>{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field :label="__('لغة الواجهة الافتراضية')" for="locale" :required="true">
                    <x-ui.select id="locale" name="locale">
                        @foreach(config('locales.supported') as $code => $lang)
                            <option value="{{ $code }}" @selected(old('locale', $tenant->locale) === $code)>{{ $lang['native'] }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field :label="__('شكل الأرقام')" for="numerals"
                            :hint="__('يظهر في الأسعار والتواريخ والتقارير.')">
                    <x-ui.select id="numerals" name="numerals">
                        <option value="arabic" @selected(setting('appearance.numerals', 'arabic') === 'arabic')>{{ __('عربية (123)') }}</option>
                        <option value="hindi" @selected(setting('appearance.numerals') === 'hindi')>{{ __('هندية (١٢٣)') }}</option>
                    </x-ui.select>
                </x-ui.field>
            </div>

            <div class="flex justify-between mt-2">
                <x-ui.button variant="ghost" :href="url('/onboarding/identity')">{{ __('رجوع') }}</x-ui.button>
                <x-ui.button type="submit" size="lg">{{ __('التالي') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.onboarding>
