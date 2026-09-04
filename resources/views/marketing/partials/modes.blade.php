@php
    /*
     | ما يظهر لكل نمط. المصدر الحيّ هو config/platform-modes.php،
     | وهذه ترجمة تسويقية لما يفعله ذلك الملف فعلاً.
     */
    $modeCopy = [
        'solo' => [
            'for' => 'مدرّس يبيع كورساته باسمه',
            'gets' => ['كورسات وأقسام ودروس واختبارات وشهادات', 'متجر ومدونة وصفحات هبوط', 'اشتراكات متكررة وتسويق بالعمولة'],
            'hides' => 'تعدّد المدرّسين والعمولات وإدارة السنتر',
        ],
        'teacher' => [
            'for' => 'مدرّس يُدرّس أونلاين وفي البيت وفي سناتر لا يملكها',
            'gets' => ['مجموعات وحصص وحضور بالكود', 'أقساط ومتأخرات وإيصالات', 'بوابة أولياء الأمور والتقرير الشهري'],
            'hides' => 'الفروع والقاعات وموظفو السنتر — تلك أدوات صاحب السنتر لا أدواتك',
        ],
        'marketplace' => [
            'for' => 'أكاديمية أو منصّة يبيع عليها مدرّسون',
            'gets' => ['تسجيل مدرّسين وموافقة على كورساتهم', 'عمولات وتحويلات ولوحة لكل مدرّس', 'تقييمات ومراجعات ومجتمع'],
            'hides' => 'إدارة السنتر الأرضي',
        ],
        'center' => [
            'for' => 'سنتر تعليمي أرضي بفروع وقاعات وموظفين',
            'gets' => ['فروع وقاعات ومجموعات وجداول بلا تعارض', 'حضور وأقساط وخزنة ومصروفات ورواتب', 'أولياء أمور ومخزون وعُهد وموظفون'],
            'hides' => 'متجر الكورسات العام — اختياري، ويُفتح متى شئت',
        ],
        'hybrid' => [
            'for' => 'من يجمع الأونلاين والحضوري والخدمات معاً',
            'gets' => ['كل ما سبق مجتمعاً في لوحة واحدة', 'خدمات وحجوزات ومنتجات رقمية ومادية', 'قمع تسويقي وأتمتة وتسويق بالعمولة'],
            'hides' => 'لا شيء',
        ],
    ];
    $first = array_key_first($modes);
@endphp

<x-marketing.section
    id="modes"
    tone="sunken"
    :eyebrow="__('لمن هذه المنصّة')"
    :title="__('خمسة أنماط — ولوحة تُبنى على نمطك أنت')"
    :lead="__('الشمول يصير تعقيداً إن رأيت كل شيء. اختر نمطك في معالج التهيئة، فتُفعَّل موديولاتك وتُضبط إعداداتك ويُختار ثيمك وتُبنى قوائمك — وترى ما يخصّك وحده.')"
>
    <div x-data="{ mode: @js($first) }" class="min-w-0">

        {{-- المبدّل: شريط رقائق يمرّر أفقياً على الموبايل بدل أن يكسر الصفحة --}}
        <div class="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 pb-2 mb-6">
            <div class="flex items-stretch gap-2 w-max mx-auto" role="tablist" aria-label="{{ __('أنماط المنصّة') }}">
                @foreach($modes as $key => $mode)
                    <button type="button" role="tab" @click="mode = @js($key)"
                            id="mode-tab-{{ $key }}" aria-controls="mode-panel-{{ $key }}"
                            :aria-selected="mode === @js($key)"
                            :class="mode === @js($key)
                                ? 'bg-spot text-on-spot border-spot shadow-md'
                                : 'bg-surface text-muted border-line hover:border-line-strong hover:text-content'"
                            class="flex items-center gap-2.5 rounded-full border px-4 py-2.5 text-sm font-bold whitespace-nowrap transition-colors">
                        <span class="text-lg leading-none" aria-hidden="true">{{ $mode['icon'] }}</span>
                        {{ $mode['name'] }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- اللوح: واحد كبير لا خمسة صغار، فيتنفّس المحتوى --}}
        @foreach($modes as $key => $mode)
            @php $copy = $modeCopy[$key] ?? null; @endphp
            {{-- اللوح الأول مرسوم من الخادم والباقي مطويّ: لا وميض قبل تحميل Alpine --}}
            <div x-show="mode === @js($key)" role="tabpanel"
                 id="mode-panel-{{ $key }}" aria-labelledby="mode-tab-{{ $key }}"
                 @if($key !== $first) style="display: none" @endif
                 class="rounded-2xl border border-line bg-surface overflow-hidden shadow-sm">
                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">

                    {{-- العمود المعرّف: لوح داكن يعطي القسم ثقلاً بصرياً --}}
                    <div class="bg-spot text-on-spot p-7 sm:p-9 flex flex-col justify-center min-w-0">
                        <span class="text-5xl leading-none mb-5" aria-hidden="true">{{ $mode['icon'] }}</span>
                        <h3 class="font-display text-2xl sm:text-3xl font-extrabold mb-3">{{ $mode['name'] }}</h3>
                        <p class="text-on-spot-muted leading-relaxed mb-6">{{ $mode['summary'] }}</p>

                        @if($copy)
                            <p class="text-sm text-on-spot-muted border-t border-spot-line pt-5">
                                <span class="font-bold text-on-spot-accent">{{ __('لمن؟') }}</span>
                                {{ __($copy['for']) }}
                            </p>
                        @endif
                    </div>

                    <div class="p-7 sm:p-9 min-w-0">
                        <p class="text-xs font-bold text-subtle mb-4">
                            {{ trans_choice('{1} موديول واحد يُفعَّل تلقائياً|{2} موديولان يُفعَّلان تلقائياً|[3,10] :count موديولات تُفعَّل تلقائياً|[11,*] :count موديولاً يُفعَّل تلقائياً', $mode['modules'], ['count' => $mode['modules']]) }}
                        </p>

                        @if($copy)
                            <ul class="grid gap-3.5 mb-7">
                                @foreach($copy['gets'] as $item)
                                    <li class="flex items-start gap-3">
                                        <span class="size-6 rounded-full grid place-items-center bg-success-subtle text-success text-xs shrink-0 mt-0.5" aria-hidden="true">✓</span>
                                        <span class="min-w-0 leading-relaxed">{{ __($item) }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            <p class="rounded-xl bg-surface-sunken border border-line px-4 py-3 text-sm text-muted leading-relaxed">
                                <span class="font-bold text-content">{{ __('ويُخفى عنك:') }}</span> {{ __($copy['hides']) }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        {{-- البُعد الثاني: نوع التقديم — مستقل عن النمط --}}
        <p class="text-center text-sm text-muted mt-7 leading-relaxed max-w-[64ch] mx-auto">
            <span class="font-bold text-content">{{ __('وبُعد ثانٍ مستقل عن النمط:') }}</span>
            {{ __('كيف تُقدّم؟') }}
            @foreach(config('platform-modes.delivery', []) as $delivery)
                <span class="inline-flex items-center rounded-full bg-primary-subtle text-primary px-2.5 py-0.5 text-xs font-bold mx-0.5">{{ $delivery['name'][app()->getLocale()] ?? $delivery['name']['ar'] }}</span>
            @endforeach
            — {{ __('وهو ما يحدّد الحقول واللوحات التي تظهر عند إنشاء الكورس.') }}
        </p>
    </div>
</x-marketing.section>
