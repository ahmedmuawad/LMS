@props(['content' => [], 'settings' => []])
@php
    $t = fn (string $key): ?string => is_array($content[$key] ?? null)
        ? ($content[$key][app()->getLocale()] ?? $content[$key][config('locales.default', 'ar')] ?? null)
        : ($content[$key] ?? null);

    $items = collect(preg_split('/\R/', (string) ($content['items'] ?? '')))
        ->filter()
        ->map(function (string $line): array {
            $parts = array_map('trim', explode('|', $line));

            return ['icon' => $parts[0] ?? '✦', 'title' => $parts[1] ?? '', 'body' => $parts[2] ?? ''];
        });
    $columns = (int) ($content['columns'] ?? 3);
@endphp

@if($t('heading'))
    <h2 class="text-2xl font-bold mb-6 text-center">{{ $t('heading') }}</h2>
@endif

<div @class([
    'grid gap-5 sm:grid-cols-2',
    'lg:grid-cols-3' => $columns === 3,
    'lg:grid-cols-4' => $columns === 4,
])>
    @foreach($items as $item)
        <div class="surface-card p-5">
            <span class="size-11 rounded-lg grid place-items-center text-xl bg-primary-subtle text-primary mb-3"
                  aria-hidden="true">{{ $item['icon'] }}</span>
            <h3 class="font-bold mb-1.5">{{ $item['title'] }}</h3>
            <p class="text-sm text-muted leading-relaxed">{{ $item['body'] }}</p>
        </div>
    @endforeach
</div>
