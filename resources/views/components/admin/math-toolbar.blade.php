@props(['label' => null])
@php
    $groups = config('math-symbols.groups', []);
    $templates = config('math-symbols.templates', []);
    $first = array_key_first($groups);
@endphp

{{--
    لوحة رموز واحدة تخدم كل حقول المعادلات في النموذج.

    تتبع آخر حقل لمسه المدرّس، فتعمل مع نصّ السؤال وخياراته وخطوات
    حلّه بالتساوي — وشاشةٌ فيها ثماني لوحات لا تُستعمل.
--}}
{{--
    لا حاجز يحفظ مكانها حين ترسو: الحشو أسفل الصفحة (`body.math-docked`)
    هو ما يفسح لها، وحاجزٌ فوقه يترك فجوة بيضاء في وسط النموذج.
--}}
<div x-data="mathEditor('{{ $first }}')" x-cloak data-math-toolbar
     {{ $attributes->merge(['class' => 'mb-4']) }}>
<div class="rounded-lg border border-line bg-surface-sunken overflow-hidden transition-shadow"
     x-bind:class="docked
        ? 'fixed inset-x-0 bottom-0 z-30 rounded-b-none shadow-[0_-8px_24px_rgb(0_0_0/0.25)] max-h-[60vh] overflow-y-auto'
        : ''">

    <button type="button" @click="open = ! open"
            class="w-full flex items-center justify-between gap-3 px-4 min-h-12 text-start hover:bg-surface transition-colors"
            :aria-expanded="open ? 'true' : 'false'" aria-controls="math-palette">
        <span class="flex items-center gap-2 min-w-0">
            <span class="size-7 shrink-0 grid place-items-center rounded-md bg-primary-subtle text-primary font-bold"
                  aria-hidden="true">∑</span>
            <span class="text-sm font-semibold truncate">{{ $label ?? __('محرّر المعادلات') }}</span>
            <span class="text-2xs text-subtle truncate hidden sm:inline" x-show="fieldLabel"
                  x-text="'← ' + fieldLabel"></span>
        </span>
        <span class="flex items-center gap-2 shrink-0">
            <span class="text-2xs text-subtle hidden sm:inline" x-show="docked">{{ __('مرساة أثناء الكتابة') }}</span>
            <span class="text-muted" aria-hidden="true" x-text="open ? '▾' : '▴'"></span>
        </span>
    </button>

    <div id="math-palette" x-show="open" x-collapse>
        <div class="border-t border-line p-3 grid gap-3">

            {{-- تبويبات المجموعات: شريط يُمرَّر أفقياً لا يُكسر السطر --}}
            <div class="overflow-x-auto -mx-1 px-1">
                <div class="flex gap-1.5 w-max" role="tablist" aria-label="{{ __('مجموعات الرموز') }}">
                    @foreach($groups as $key => $group)
                        <button type="button" role="tab" @click="group = '{{ $key }}'"
                                :aria-selected="group === '{{ $key }}' ? 'true' : 'false'"
                                class="inline-flex items-center gap-1.5 min-h-11 px-3 rounded-md text-xs font-medium whitespace-nowrap border transition-colors"
                                :class="group === '{{ $key }}'
                                    ? 'bg-primary text-primary-on border-primary'
                                    : 'border-line-strong text-muted hover:bg-surface'">
                            <span data-math aria-hidden="true">${{ $group['icon'] }}$</span>
                            {{ __($group['label']) }}
                        </button>
                    @endforeach
                </div>
            </div>

            @foreach($groups as $key => $group)
                <div x-show="group === '{{ $key }}'" role="tabpanel"
                     class="grid gap-1.5 grid-cols-[repeat(auto-fill,minmax(52px,1fr))]">
                    @foreach($group['symbols'] as $symbol)
                        <button type="button"
                                @click="insert(@js($symbol['tex']))"
                                class="min-h-12 px-1 grid place-items-center rounded-md border border-line-strong bg-surface
                                       hover:bg-primary-subtle hover:border-primary transition-colors"
                                title="{{ __($symbol['label']) }}"
                                aria-label="{{ __($symbol['label']) }}">
                            <span data-math class="text-sm pointer-events-none" aria-hidden="true">${{ $symbol['preview'] }}$</span>
                        </button>
                    @endforeach
                </div>
            @endforeach

            @if($templates !== [])
                <div>
                    <p class="text-2xs font-semibold text-muted mb-1.5">{{ __('قوالب جاهزة') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($templates as $template)
                            <button type="button" @click="insert(@js($template['tex']))"
                                    class="inline-flex items-center gap-2 min-h-11 px-3 rounded-md border border-line-strong bg-surface
                                           hover:bg-primary-subtle hover:border-primary transition-colors"
                                    aria-label="{{ __($template['label']) }}">
                                <span data-math class="text-sm pointer-events-none" aria-hidden="true">${{ $template['preview'] }}$</span>
                                <span class="text-2xs text-muted">{{ __($template['label']) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                <p class="text-2xs text-subtle">
                    {{ __('اكتب النصّ كالمعتاد، واضغط رمزاً لتبدأ معادلة عند المؤشّر. المعادلة تُرسم في مكانها — اضغطها لتعديلها.') }}
                    <span class="inline-flex items-center gap-1 ms-1">
                        <span data-math aria-hidden="true">$\square$</span>
                        {{ __('= مكان فارغ تكتب فيه.') }}
                    </span>
                </p>
                {{-- التنقّل بين الفراغات: الكسر خانتان والمصفوفة أربع --}}
                <button type="button" @click="nextHole()"
                        class="inline-flex items-center gap-1.5 min-h-11 px-3 rounded-md text-xs font-medium
                               text-primary hover:bg-primary-subtle transition-colors">
                    <span data-math aria-hidden="true">$\square$</span>
                    {{ __('الفراغ التالي') }}
                </button>
            </div>
        </div>
    </div>
</div>
</div>
