<x-marketing.section
    id="payments"
    tone="sunken"
    :eyebrow="__('أربع عملات في المعاملة الواحدة — والنظام يعرف الفرق')"
    :title="__('تبيع بعملة بلدك، وتُفوتر بضريبته، وتُحصّل ببوابته')"
    :lead="__('عملة العرض للزائر، وعملة التحصيل للبوابة، وعملة التسوية للبنك، وعملة الدفاتر لك. أربع لا تُخلط، وفوترة إلكترونية حيث يفرضها القانون.')"
>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach(config('marketing.countries', []) as $country)
            <article class="rounded-2xl border border-line bg-surface p-5 min-w-0 lift">
                <div class="flex items-center gap-3 mb-3">
                    <span class="text-3xl leading-none shrink-0" aria-hidden="true">{{ $country['flag'] }}</span>
                    <div class="min-w-0">
                        <h3 class="font-bold leading-tight truncate">{{ __($country['name']) }}</h3>
                        <p class="text-2xs font-mono text-subtle">{{ $country['currency'] }}</p>
                    </div>
                    @if($country['invoice'] !== '—')
                        <span class="ms-auto shrink-0 text-2xs font-bold px-2 py-0.5 rounded-full bg-info-subtle text-info">{{ __($country['invoice']) }}</span>
                    @endif
                </div>
                <p class="text-sm text-muted leading-relaxed">{{ $country['gateways'] }}</p>
            </article>
        @endforeach
    </div>

    <p class="text-center text-2xs text-subtle mb-12 max-w-[70ch] mx-auto leading-relaxed">
        {{ __('النسب الضريبية وقواعدها إعدادات في لوحة الإدارة لا ثوابت في الكود، وتُراجع مع مستشار ضريبي في كل دولة قبل التفعيل.') }}
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        @foreach([
            ['icon' => '🎟️', 't' => 'أكواد الشحن (الكروت)', 'b' => 'نموذج التسييل الأول في مصر: توليد وطباعة وتوزيع على المكتبات، وتفعيل الطالب بالكود، وتتبّع كل كارت أين ذهب ومتى استُخدم.'],
            ['icon' => '🧾', 't' => 'تقسيط ومحفظة وتحويل بنكي', 'b' => 'أقساط بمواعيد استحقاق، ومحفظة رصيد داخلية للطالب، وتحويل بنكي برفع إيصال واعتماد يدوي — لمن لا يملك بطاقة.'],
            ['icon' => '🤝', 't' => 'عمولات المدرّسين وتحويلاتهم', 'b' => 'نِسب لكل مدرّس، ومستحقات تُحسب من مبيعاته أو من حضوره الفعلي، وتحويلات متعددة العملات بخصم عند المنبع.'],
        ] as $item)
            <div class="relative overflow-hidden rounded-2xl border border-line bg-surface p-6 min-w-0">
                <div class="pointer-events-none absolute inset-0" aria-hidden="true"
                     style="background: radial-gradient(22rem 12rem at 90% 0%, var(--color-primary-subtle), transparent 65%);"></div>
                <div class="relative">
                    <span class="inline-grid place-items-center size-12 rounded-2xl bg-accent-subtle text-xl mb-4" aria-hidden="true">{{ $item['icon'] }}</span>
                    <h3 class="font-bold text-lg mb-2">{{ __($item['t']) }}</h3>
                    <p class="text-muted leading-relaxed">{{ __($item['b']) }}</p>
                </div>
            </div>
        @endforeach
    </div>
</x-marketing.section>
