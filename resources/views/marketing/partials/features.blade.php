@php
    $groups = array_values(config('marketing.feature_groups', []));
@endphp

<x-marketing.section
    id="features"
    tone="sunken"
    :eyebrow="__('64 موديولاً · كلٌّ بصفحة إعدادات كاملة')"
    :title="__('كل ميزة مبنية في النظام — والباقة هي ما يقرّر إتاحتها')"
    :lead="__('لا إضافات تشتريها من طرف ثالث ولا تعارض نسخ. كل موديول يُفعَّل أو يُعطَّل بضغطة، وله إعداداته وصلاحياته وترجماته وسجل تدقيقه.')"
>
    <div x-data="{ group: 0 }" class="grid grid-cols-1 lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] gap-5 lg:gap-8 items-start">

        {{--
            القائمة: عمود جانبي على الشاشات الواسعة، وشريط رقائق يمرّر
            أفقياً تحت lg — لا قائمة عمودية تأكل شاشة الموبايل (وثيقة 13، قاعدة 5).
        --}}
        <div class="lg:sticky lg:top-20 min-w-0">
            <div class="overflow-x-auto -mx-4 px-4 lg:mx-0 lg:px-0 lg:overflow-visible pb-2 lg:pb-0">
                <div class="flex lg:flex-col gap-2 w-max lg:w-auto" role="tablist" aria-label="{{ __('مجموعات المميزات') }}">
                    @foreach($groups as $index => $group)
                        <button type="button" role="tab" @click="group = {{ $index }}"
                                id="feature-tab-{{ $index }}" aria-controls="feature-panel-{{ $index }}"
                                :aria-selected="group === {{ $index }}"
                                :class="group === {{ $index }}
                                    ? 'bg-spot text-on-spot border-spot shadow-md'
                                    : 'bg-surface text-muted border-line hover:border-line-strong hover:text-content'"
                                class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-bold whitespace-nowrap lg:whitespace-normal text-start transition-colors lg:w-full">
                            <span class="text-lg leading-none shrink-0" aria-hidden="true">{{ $group['icon'] }}</span>
                            <span class="min-w-0">{{ __($group['title']) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- اللوح: مجموعة واحدة كاملة بخطّ كبير مقروء، لا عشر بطاقات مزدحمة --}}
        <div class="min-w-0">
            @foreach($groups as $index => $group)
                <div x-show="group === {{ $index }}" role="tabpanel"
                     id="feature-panel-{{ $index }}" aria-labelledby="feature-tab-{{ $index }}"
                     @if($index !== 0) style="display: none" @endif
                     class="rounded-2xl border border-line bg-surface p-6 sm:p-9 min-w-0">

                    <div class="flex items-center gap-4 pb-6 mb-6 border-b border-line">
                        <span class="inline-grid place-items-center size-14 rounded-2xl bg-accent-subtle text-2xl shrink-0" aria-hidden="true">{{ $group['icon'] }}</span>
                        <h3 class="font-display text-xl sm:text-2xl font-extrabold min-w-0">{{ __($group['title']) }}</h3>
                    </div>

                    <ul class="grid sm:grid-cols-2 gap-x-8 gap-y-4">
                        @foreach($group['items'] as $item)
                            <li class="flex items-start gap-3 min-w-0">
                                <span class="size-6 rounded-full grid place-items-center bg-primary-subtle text-primary text-xs shrink-0 mt-0.5" aria-hidden="true">✓</span>
                                <span class="min-w-0 leading-relaxed">{{ __($item) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <p class="text-sm text-muted mt-6 leading-relaxed">
                {{ __('وما لا تشمله باقتك لا يختفي من لوحتك: يظهر بقفل وشرح وزر ترقية. الميزة المخفية لا تُباع، والميزة المُربكة لا تُستخدم.') }}
            </p>
        </div>
    </div>
</x-marketing.section>
