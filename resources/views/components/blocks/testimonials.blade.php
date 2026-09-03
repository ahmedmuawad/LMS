@props(['content' => [], 'settings' => []])
@php
    $t = fn (string $key): ?string => is_array($content[$key] ?? null)
        ? ($content[$key][app()->getLocale()] ?? $content[$key][config('locales.default', 'ar')] ?? null)
        : ($content[$key] ?? null);

    $items = collect(preg_split('/\R/', (string) ($content['items'] ?? '')))
        ->filter()
        ->map(function (string $line): array {
            $parts = array_map('trim', explode('|', $line));

            return ['name' => $parts[0] ?? '', 'role' => $parts[1] ?? '', 'quote' => $parts[2] ?? ''];
        });
@endphp

@if($t('heading'))
    <h2 class="text-2xl font-bold mb-6 text-center">{{ $t('heading') }}</h2>
@endif

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($items as $item)
        <figure class="surface-card p-5">
            <blockquote class="text-sm leading-relaxed text-muted mb-4">{{ $item['quote'] }}</blockquote>
            <figcaption class="flex items-center gap-3">
                <x-ui.avatar :name="$item['name']" size="sm" />
                <span class="min-w-0">
                    <span class="block text-sm font-semibold truncate">{{ $item['name'] }}</span>
                    <span class="block text-2xs text-subtle truncate">{{ $item['role'] }}</span>
                </span>
            </figcaption>
        </figure>
    @endforeach
</div>
