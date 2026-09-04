<x-marketing.section
    id="center"
    tone="spot"
    align="start"
    :eyebrow="__('أكبر فارق تنافسي في المنتج')"
    :title="__('سنترك بكامله — لا وحدة حضور مضافة على منصّة كورسات')"
    :lead="__('منافسو الكورسات الأونلاين لا يفهمون السنتر، وأنظمة السناتر لا تعرف بيع الكورسات. هنا الاثنان نظام واحد بقاعدة طلاب واحدة.')"
>
    {{-- 1) المشكلة اليومية ← الحل: قائمة تحريرية لا جدول --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8 mb-14">
        @foreach(config('marketing.center.problems', []) as $index => $item)
            <div class="flex gap-4 min-w-0">
                <span class="font-display text-3xl font-extrabold text-on-spot-accent/50 leading-none shrink-0 tabular"
                      aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                <div class="min-w-0">
                    <p class="font-bold text-lg mb-1.5 leading-snug">{{ __($item['problem']) }}</p>
                    <p class="text-on-spot-muted leading-relaxed">{{ __($item['solution']) }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- 2) الحضور والتقرير: لوحان فاتحان على الخلفية الداكنة — تباين يجذب العين --}}
    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] gap-6">

        <div class="rounded-2xl bg-surface text-content overflow-hidden min-w-0">
            <div class="px-6 py-5 border-b border-line">
                <h3 class="font-display text-lg font-extrabold">{{ __('الحضور بست طرق مبنية بالكامل') }}</h3>
                <p class="text-sm text-muted mt-1">{{ __('اختر ما يناسب حجم مجموعتك وجهازك — وكلّها تكتب في السجل نفسه.') }}</p>
            </div>

            <ul class="divide-y divide-[var(--color-line)]">
                @foreach(config('marketing.center.attendance', []) as $row)
                    <li class="flex items-center gap-4 px-6 py-3.5 min-w-0">
                        <span class="font-bold text-sm shrink-0 w-24">{{ __($row['method']) }}</span>
                        <span class="text-sm text-muted min-w-0 flex-1">{{ __($row['how']) }}</span>
                        <span class="text-2xs text-subtle shrink-0 hidden sm:block">{{ __($row['use']) }}</span>
                    </li>
                @endforeach
            </ul>

            <p class="px-6 py-4 bg-surface-sunken border-t border-line text-sm text-muted leading-relaxed">
                {{ __('وحضور المدرّسين بالآلية نفسها — وهو أساس حساب رواتبهم ونِسبهم آلياً.') }}
            </p>
        </div>

        {{-- محاكاة التقرير الشهري: أرقام العرض من وثيقة 16 نفسها --}}
        <div class="min-w-0">
            <div class="rounded-2xl bg-surface text-content p-6 shadow-xl">
                <div class="flex items-baseline justify-between gap-3 pb-4 mb-4 border-b border-line">
                    <div class="min-w-0">
                        <p class="font-bold truncate">{{ __('يوسف حمدي') }}</p>
                        <p class="text-2xs text-subtle truncate">{{ __('ثانوية · فيزياء · تقرير سبتمبر') }}</p>
                    </div>
                    <span class="text-2xs font-bold text-primary shrink-0">{{ __('يُرسل تلقائياً') }}</span>
                </div>

                <dl class="grid gap-3.5">
                    @foreach([
                        ['label' => 'الحضور', 'value' => '14 من 16', 'pct' => 87, 'tone' => 'attended'],
                        ['label' => 'متوسط الدرجات', 'value' => '78٪', 'pct' => 78, 'tone' => 'progress'],
                        ['label' => 'الواجبات', 'value' => '9 من 12', 'pct' => 75, 'tone' => 'completed'],
                    ] as $bar)
                        <div class="min-w-0">
                            <div class="flex items-baseline justify-between gap-2 mb-1.5">
                                <dt class="text-sm text-muted">{{ __($bar['label']) }}</dt>
                                <dd class="text-sm font-bold tabular">{{ $bar['value'] }}</dd>
                            </div>
                            <div class="h-2 rounded-full bg-surface-sunken overflow-hidden" role="presentation">
                                <div class="h-full rounded-full bg-{{ $bar['tone'] }}" style="inline-size: {{ $bar['pct'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-5 pt-4 border-t border-line grid gap-2.5 text-sm">
                    @foreach([
                        ['الترتيب في المجموعة', '7 من 28', ''],
                        ['مقارنة بالشهر الماضي', '▲ 6٪', 'text-success'],
                        ['موقف المصروفات', 'متأخر 650 ج.م', 'text-overdue'],
                    ] as [$label, $value, $tone])
                        <p class="flex items-center justify-between gap-3">
                            <span class="text-muted">{{ __($label) }}</span>
                            <span class="font-bold {{ $tone }}">{{ __($value) }}</span>
                        </p>
                    @endforeach
                </div>
            </div>

            <p class="text-sm text-on-spot-muted leading-relaxed mt-5">
                {{ __('يُولَّد لكل طالب آلياً، ويُطبع PDF أو يُرسل بالواتساب والرسائل والبريد — إرسال جماعي لكل أولياء أمور المجموعة بضغطة، مع تتبّع من فتح الرسالة.') }}
            </p>
        </div>
    </div>
</x-marketing.section>
