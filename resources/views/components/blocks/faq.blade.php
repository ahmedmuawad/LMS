@props(['content' => [], 'settings' => []])
@php
    $t = fn (string $key): ?string => is_array($content[$key] ?? null)
        ? ($content[$key][app()->getLocale()] ?? $content[$key][config('locales.default', 'ar')] ?? null)
        : ($content[$key] ?? null);

    $items = collect(preg_split('/\R/', (string) ($content['items'] ?? '')))
        ->filter()
        ->map(function (string $line): array {
            $parts = array_map('trim', explode('|', $line));

            return ['q' => $parts[0] ?? '', 'a' => $parts[1] ?? ''];
        });
@endphp

@if($t('heading'))
    <h2 class="text-2xl font-bold mb-6 text-center">{{ $t('heading') }}</h2>
@endif

<div class="max-w-[52rem] mx-auto divide-y divide-[var(--color-line)] surface-card">
    @foreach($items as $item)
        <details class="group">
            <summary class="flex items-center justify-between gap-3 px-5 py-4 cursor-pointer hover:bg-surface-sunken transition-colors">
                <span class="font-semibold text-sm">{{ $item['q'] }}</span>
                <span class="text-subtle shrink-0 transition-transform group-open:rotate-45" aria-hidden="true">＋</span>
            </summary>
            <p class="px-5 pb-4 text-sm text-muted leading-relaxed">{{ $item['a'] }}</p>
        </details>
    @endforeach
</div>

@if($content['schema'] ?? true)
    @push('head')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $items->map(fn (array $i): array => [
            '@type' => 'Question',
            'name' => $i['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['a']],
        ])->values()->all(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endpush
@endif
