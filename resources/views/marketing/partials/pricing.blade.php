@php
    $locale = app()->getLocale();
    $defaultCurrency = $currencies[0] ?? 'EGP';

    /*
     | نبني بيانات العرض مرة واحدة هنا بدل استدعاء الموديل داخل حلقات
     | القالب. التسعير مثبّت لكل عملة (ADR-014)، فمبدّل العملة يقلّب
     | مصفوفة جاهزة في المتصفح بلا طلب جديد ولا حساب صرف.
     */
    $cards = $plans->map(function ($plan) use ($currencies, $featureNames, $headlineLimits, $headlineFeatures, $modes, $locale): array {
        $values = $plan->features->pluck('value', 'feature_key');

        $prices = [];
        foreach ($currencies as $currency) {
            $money = $plan->priceIn($currency);

            /*
             | الكسور صفر في كل باقاتنا، و«1,499.00» على صفحة أسعار
             | ضجيج لا دقّة — نحذفها متى كانت أصفاراً وحدها.
             */
            $prices[$currency] = $money === null
                ? __('حسب الطلب')
                : preg_replace('/\.0+(?=\D|$)/u', '', $money->format($locale));
        }

        $limits = [];
        foreach ($headlineLimits as $key) {
            if (! $values->has($key)) {
                continue;
            }

            $raw = (string) $values[$key];
            $limits[] = [
                'label' => $featureNames[$key]['name'] ?? $key,
                'value' => $raw === 'unlimited'
                    ? __('بلا حدّ')
                    : trim($raw.' '.($featureNames[$key]['unit'] ?? '')),
            ];
        }

        // المزايا المنطقية وحدها (قيمتها "1") — أما الحدود فقد عُرضت أعلاه
        $enabled = $values->filter(fn ($value): bool => (string) $value === '1')->keys();
        $shown = collect($headlineFeatures)->filter(fn (string $key): bool => $enabled->contains($key))->take(8);

        return [
            'key' => $plan->key,
            'name' => $plan->name[$locale] ?? $plan->name['ar'] ?? $plan->key,
            'tagline' => $plan->tagline[$locale] ?? $plan->tagline['ar'] ?? '',
            'trial' => (int) $plan->trial_days,
            'prices' => $prices,
            'limits' => $limits,
            'modes' => collect($plan->modes ?? [])
                ->map(fn (string $mode): string => $modes[$mode]['name'] ?? $mode)
                ->all(),
            'features' => $shown->map(fn (string $key): string => $featureNames[$key]['name'] ?? $key)->values()->all(),
            'more' => max(0, $enabled->count() - $shown->count()),
        ];
    });

    // خريطة الأسعار للمتصفح: {plan_key: {currency: "نص السعر"}}
    $priceMap = $cards->mapWithKeys(fn (array $card): array => [$card['key'] => $card['prices']])->all();

    // «الأكثر طلباً» — البطاقة المميّزة بصرياً
    $highlight = $plans->firstWhere('key', 'growth')?->key ?? $plans->get(1)?->key;
@endphp

<x-marketing.section
    id="pricing"
    tone="tint"
    :eyebrow="__('تسعير مثبّت لكل عملة — لا تحويل بسعر صرف متقلّب')"
    :title="__('اشتراك شهري واحد. وصفر عمولة على ما تبيعه.')"
    :lead="__('كل باقة تشمل نطاقاً فرعياً فورياً وقاعدة بيانات مستقلة ونسخاً احتياطياً وتصديراً كاملاً لبياناتك — وتجربة مجانية قبل أي دفع.')"
>
    @if($cards->isEmpty())
        <div class="surface-card p-8 text-center max-w-[42rem] mx-auto">
            <p class="font-bold mb-2">{{ __('الباقات قيد الإعداد') }}</p>
            <p class="text-sm text-muted leading-relaxed mb-5">{{ __('تواصل معنا وسنجهّز لك عرضاً على مقاس نمطك وحجم طلابك.') }}</p>
            <x-ui.button :href="'mailto:'.config('marketing.brand.email')">{{ __('اطلب عرضاً') }}</x-ui.button>
        </div>
    @else
        <div x-data="{ currency: @js($defaultCurrency), prices: @js($priceMap) }">

            @if(count($currencies) > 1)
                <div class="flex justify-center mb-8">
                    <div class="inline-flex items-center gap-1 p-1 rounded-full bg-surface-sunken border border-line"
                         role="group" aria-label="{{ __('اختر العملة') }}">
                        @foreach($currencies as $currency)
                            <button type="button" @click="currency = @js($currency)"
                                    :class="currency === @js($currency) ? 'bg-primary text-primary-on' : 'text-muted hover:text-content'"
                                    :aria-pressed="currency === @js($currency)"
                                    class="px-4 py-1.5 rounded-full text-xs font-bold font-mono transition-colors min-h-9">{{ $currency }}</button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 items-start">
                @foreach($cards as $card)
                    @php $featured = $card['key'] === $highlight; @endphp
                    <article @class([
                        'relative flex flex-col min-w-0 rounded-2xl border',
                        'bg-spot text-on-spot border-spot shadow-xl xl:-my-4' => $featured,
                        'bg-surface border-line lift' => ! $featured,
                    ])>
                        @if($featured)
                            <span class="absolute -top-3 start-6 px-3 py-1 rounded-full bg-accent text-accent-on text-2xs font-bold shadow-md">
                                {{ __('الأكثر طلباً') }}
                            </span>
                        @endif

                        <div @class(['p-6', 'border-b border-spot-line' => $featured, 'border-b border-line' => ! $featured])>
                            <h3 class="font-display font-extrabold text-xl">{{ $card['name'] }}</h3>
                            <p @class(['text-xs mt-1 min-h-8', 'text-on-spot-muted' => $featured, 'text-subtle' => ! $featured])>{{ $card['tagline'] }}</p>

                            <p class="mt-5 mb-1.5 flex items-baseline gap-1.5 flex-wrap">
                                {{-- الخادم يرسم سعر العملة الافتراضية، وAlpine يبدّله بلا وميض --}}
                                <span class="font-display text-3xl font-extrabold tabular"
                                      x-text="prices[@js($card['key'])][currency]">{{ $card['prices'][$defaultCurrency] ?? '' }}</span>
                                <span @class(['text-xs', 'text-on-spot-muted' => $featured, 'text-subtle' => ! $featured])>{{ __('/ شهرياً') }}</span>
                            </p>

                            @if($card['trial'] > 0)
                                <p @class(['text-2xs font-semibold', 'text-on-spot-accent' => $featured, 'text-success' => ! $featured])>
                                    {{ __(':days يوماً تجربة مجانية', ['days' => $card['trial']]) }}
                                </p>
                            @endif

                            <x-ui.button class="w-full mt-5" :href="url('/start?plan='.$card['key'])"
                                         :variant="$featured ? 'accent' : 'secondary'">{{ __('ابدأ الآن') }}</x-ui.button>
                        </div>

                        @if($card['limits'] !== [])
                            <dl @class(['px-6 py-5 grid gap-2.5', 'border-b border-spot-line' => $featured, 'border-b border-line' => ! $featured])>
                                @foreach($card['limits'] as $limit)
                                    <div class="flex items-baseline justify-between gap-3 text-sm">
                                        <dt @class(['min-w-0 truncate', 'text-on-spot-muted' => $featured, 'text-muted' => ! $featured])>{{ $limit['label'] }}</dt>
                                        <dd class="font-bold shrink-0 tabular">{{ $limit['value'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        <div class="p-6 flex-1 flex flex-col">
                            @if($card['modes'] !== [])
                                <div class="flex flex-wrap gap-1.5 mb-4">
                                    @foreach($card['modes'] as $mode)
                                        <span @class([
                                            'inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-full leading-6',
                                            'bg-spot-raised text-on-spot' => $featured,
                                            'bg-surface-sunken text-muted' => ! $featured,
                                        ])>{{ $mode }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <ul class="grid gap-1.5">
                                @foreach($card['features'] as $feature)
                                    <li @class(['flex items-start gap-2.5 text-sm', 'text-on-spot-muted' => $featured, 'text-muted' => ! $featured])>
                                        <span @class(['shrink-0 leading-6', 'text-on-spot-accent' => $featured, 'text-success' => ! $featured]) aria-hidden="true">✓</span>
                                        <span class="min-w-0">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            @if($card['more'] > 0)
                                <p @class(['text-2xs mt-4 pt-4', 'text-on-spot-muted border-t border-spot-line' => $featured, 'text-subtle border-t border-line' => ! $featured])>
                                    {{ trans_choice('{1} وميزة أخرى مفعّلة|{2} وميزتان أخريان مفعّلتان|[3,10] و:count مزايا أخرى مفعّلة|[11,*] و:count ميزة أخرى مفعّلة', $card['more'], ['count' => $card['more']]) }}
                                </p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <p class="text-center text-xs text-subtle mt-8 max-w-[68ch] mx-auto leading-relaxed">
            {{ __('تجاوزت حدّك؟ ننبّهك عند 80٪ ثم 95٪، وعند 100٪ نمنع الإضافة الجديدة فقط — والموجود يظل يعمل بالكامل. والطالب الذي دفع لا يُحرم من محتواه أبداً بسبب حدٍّ عليك.') }}
        </p>
    @endif
</x-marketing.section>
