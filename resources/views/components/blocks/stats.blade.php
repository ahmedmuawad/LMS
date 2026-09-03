@props(['content' => [], 'settings' => []])
@php
    $t = fn (string $key): ?string => is_array($content[$key] ?? null)
        ? ($content[$key][app()->getLocale()] ?? $content[$key][config('locales.default', 'ar')] ?? null)
        : ($content[$key] ?? null);

    $items = collect(preg_split('/\R/', (string) ($content['items'] ?? '')))
        ->filter()
        ->map(function (string $line): array {
            $parts = array_map('trim', explode('|', $line));

            return ['value' => $parts[0] ?? '', 'label' => $parts[1] ?? ''];
        });
@endphp

@if($t('heading'))
    <h2 class="text-2xl font-bold mb-6 text-center">{{ $t('heading') }}</h2>
@endif

<div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
    @foreach($items as $item)
        <div class="text-center">
            <p class="font-mono text-3xl font-medium tabular text-primary">{{ $item['value'] }}</p>
            <p class="text-sm text-muted mt-1">{{ $item['label'] }}</p>
        </div>
    @endforeach
</div>
