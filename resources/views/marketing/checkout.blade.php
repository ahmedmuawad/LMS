@php
    $locale = app()->getLocale();
    $planName = $plan?->name[$locale] ?? $plan?->name['ar'] ?? $tenant->plan_key;
    $domain = $tenant->domains->firstWhere('is_primary', true)?->domain ?? $tenant->domains->first()?->domain;
@endphp

<x-layouts.marketing :title="__('منصّتك جاهزة')">
    <div class="max-w-[720px] mx-auto px-4 sm:px-6 py-10 sm:py-16">

        <div class="text-center mb-8">
            <span class="inline-grid place-items-center size-14 rounded-2xl bg-success-subtle text-success text-2xl mb-4"
                  aria-hidden="true">✓</span>
            <h1 class="text-2xl sm:text-3xl font-extrabold mb-2">{{ __('جُهّزت :name', ['name' => $tenant->name]) }}</h1>
            <p class="text-sm text-muted leading-relaxed">
                {{ __('قاعدة بيانات مستقلة ونطاق باسمك — وبقي أن تختار كيف تدفع.') }}
            </p>
            <p class="font-mono text-xs text-primary mt-2" dir="ltr">{{ $domain }}</p>
        </div>

        @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif
        @error('gateway')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror
        @error('slug')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

        <x-ui.card :title="__('اشتراكك')" class="mb-4">
            <x-ui.description-list :items="array_filter([
                __('الباقة')  => $planName,
                __('النمط')   => config('platform-modes.modes.'.$tenant->platform_mode.'.name.'.$locale)
                                 ?? config('platform-modes.modes.'.$tenant->platform_mode.'.name.ar'),
                __('المستحق') => $invoice?->total()->format($locale),
                __('التجربة') => $tenant->onTrial()
                    ? trans_choice('{1} يوم واحد مجاناً|{2} يومان مجاناً|[3,10] :count أيام مجاناً|[11,*] :count يوماً مجاناً',
                        $tenant->trialDaysLeft(), ['count' => $tenant->trialDaysLeft()])
                    : null,
                __('الفاتورة') => $invoice?->number,
            ])" />
        </x-ui.card>

        @if($trialAllowed)
            {{-- التجربة أولاً: من جاء ليجرّب لا يُطالَب ببطاقة قبل أن يرى شيئاً --}}
            <x-ui.card class="mb-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-bold mb-1">{{ __('ادخل الآن وجرّب') }}</p>
                        <p class="text-xs text-muted leading-relaxed max-w-[46ch]">
                            {{ __('منصّتك تعمل كاملة طوال التجربة بلا بطاقة. ستصلك الفاتورة قبل انتهائها.') }}
                        </p>
                    </div>
                    <form method="POST" action="{{ url('/start/'.$tenant->slug.'/trial') }}" class="shrink-0">
                        @csrf
                        <x-ui.button type="submit" size="lg">{{ __('ادخل إلى منصّتي') }}</x-ui.button>
                    </form>
                </div>
            </x-ui.card>
        @endif

        <x-ui.card :title="__('أو ادفع الآن')"
                   :subtitle="__('السداد المبكّر يُثبّت سعرك ويُنهي التذكيرات.')">
            @if($gateways === [])
                <x-ui.empty :title="__('لا طريقة دفع مفعّلة بعد')">
                    {{ __('تواصل معنا لإتمام الاشتراك — ومنصّتك تعمل في تجربتها الآن.') }}
                </x-ui.empty>
            @else
                <form method="POST" action="{{ url('/start/'.$tenant->slug.'/pay') }}" class="grid gap-3"
                      x-data="{ gateway: @js($gateways[0]->key()) }">
                    @csrf
                    @foreach($gateways as $gateway)
                        <label class="cursor-pointer">
                            <input type="radio" name="gateway" value="{{ $gateway->key() }}" x-model="gateway" class="peer sr-only">
                            <span class="flex items-start gap-3 p-3 rounded-lg border transition-colors
                                         border-line-strong hover:border-primary
                                         peer-checked:border-primary peer-checked:bg-primary-subtle">
                                <span class="size-5 mt-0.5 rounded-full border-2 grid place-items-center shrink-0
                                             peer-checked:border-primary border-line-strong" aria-hidden="true">
                                    <span class="size-2.5 rounded-full" :class="gateway === @js($gateway->key()) ? 'bg-primary' : ''"></span>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold">{{ $gateway->title() }}</span>
                                    <span class="block text-2xs text-subtle leading-relaxed mt-0.5">{{ $gateway->description() }}</span>
                                </span>
                            </span>
                        </label>
                    @endforeach

                    <div>
                        <x-ui.button type="submit">{{ __('تابع الدفع') }}</x-ui.button>
                    </div>
                </form>
            @endif
        </x-ui.card>
    </div>
</x-layouts.marketing>
