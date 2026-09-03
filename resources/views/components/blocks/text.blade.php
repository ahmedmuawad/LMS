@props(['content' => [], 'settings' => []])
@php
    $t = fn (string $key): ?string => is_array($content[$key] ?? null)
        ? ($content[$key][app()->getLocale()] ?? $content[$key][config('locales.default', 'ar')] ?? null)
        : ($content[$key] ?? null);
@endphp

@if($t('heading'))
    <h2 class="text-2xl font-bold mb-4">{{ $t('heading') }}</h2>
@endif

<div @class([
    'leading-relaxed text-muted whitespace-pre-line',
    'sm:columns-2 sm:gap-8' => ($content['columns'] ?? '1') === '2',
])>{{ $t('body') }}</div>
