@php
    $faq = config('marketing.faq', []);
    $half = (int) ceil(count($faq) / 2);
    $columns = [array_slice($faq, 0, $half), array_slice($faq, $half)];
@endphp

<x-marketing.section id="faq" tone="sunken" :eyebrow="__('قبل أن تسأل')" :title="__('الأسئلة الشائعة')">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
        @foreach($columns as $column)
            <div class="grid gap-3 content-start min-w-0">
                @foreach($column as $item)
                    <details class="group rounded-2xl border border-line bg-surface overflow-hidden">
                        <summary class="flex items-start justify-between gap-4 px-5 py-4 cursor-pointer hover:bg-surface-sunken transition-colors">
                            <span class="font-bold min-w-0 leading-relaxed">{{ __($item['q']) }}</span>
                            <span class="size-6 rounded-full grid place-items-center bg-primary-subtle text-primary text-sm shrink-0 mt-0.5 transition-transform group-open:rotate-45"
                                  aria-hidden="true">＋</span>
                        </summary>
                        <p class="px-5 pb-5 text-muted leading-relaxed">{{ __($item['a']) }}</p>
                    </details>
                @endforeach
            </div>
        @endforeach
    </div>

    @push('head')
    <script type="application/ld+json">{!! json_encode([
        '@'.'context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn (array $item): array => [
            '@type' => 'Question',
            'name' => $item['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
        ], $faq),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endpush
</x-marketing.section>
