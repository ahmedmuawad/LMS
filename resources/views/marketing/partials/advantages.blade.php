@php
    $items = config('marketing.advantages', []);
    // اثنتان تحملان الرسالة، والعشر الباقيات تسندانها — لا اثنتا عشرة بطاقة متساوية
    $lead = array_slice($items, 0, 2);
    $rest = array_slice($items, 2);
@endphp

<x-marketing.section
    id="why"
    :eyebrow="__('اثنتا عشرة نقطة لا يجمعها أحد')"
    :title="__('ما الذي يجعلنا مختلفين فعلاً؟')"
    :lead="__('لا ندّعي أننا الأرخص ولا الأقدم. ندّعي أن ما تحتاجه أنت — المدرّس أو السنتر في مصر والخليج — موجود كلّه هنا، لا موزّعاً على أربعة اشتراكات.')"
>
    {{-- اللوحان الكبيران --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        @foreach($lead as $index => $advantage)
            <article @class([
                'relative overflow-hidden rounded-2xl p-7 sm:p-9 min-w-0',
                'bg-spot text-on-spot' => $index === 0,
                'border border-line bg-surface' => $index !== 0,
            ])>
                @if($index !== 0)
                    <div class="pointer-events-none absolute inset-0" aria-hidden="true"
                         style="background: radial-gradient(28rem 16rem at 88% 0%, var(--color-accent-subtle), transparent 62%);"></div>
                @endif

                <div class="relative">
                    <span @class([
                        'inline-grid place-items-center size-14 rounded-2xl text-2xl mb-5',
                        'bg-spot-raised' => $index === 0,
                        'bg-accent-subtle' => $index !== 0,
                    ]) aria-hidden="true">{{ $advantage['icon'] }}</span>

                    <h3 class="font-display text-xl sm:text-2xl font-extrabold mb-3 leading-snug">{{ __($advantage['title']) }}</h3>
                    <p @class([
                        'leading-relaxed max-w-[46ch]',
                        'text-on-spot-muted' => $index === 0,
                        'text-muted' => $index !== 0,
                    ])>{{ __($advantage['body']) }}</p>
                </div>
            </article>
        @endforeach
    </div>

    {{-- العشر الباقيات: صفوف بخط فاصل رفيع لا بطاقات محاطة — أخفّ على العين --}}
    <div class="rounded-2xl border border-line bg-surface overflow-hidden">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-px bg-line">
            @foreach($rest as $index => $advantage)
                <article class="bg-surface p-6 sm:p-7 min-w-0 flex gap-4">
                    <span class="inline-grid place-items-center size-11 rounded-xl bg-primary-subtle text-primary text-lg shrink-0" aria-hidden="true">{{ $advantage['icon'] }}</span>
                    <div class="min-w-0">
                        <h3 class="font-bold mb-1.5 leading-snug">{{ __($advantage['title']) }}</h3>
                        <p class="text-sm text-muted leading-relaxed">{{ __($advantage['body']) }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</x-marketing.section>
