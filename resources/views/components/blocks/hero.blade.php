@props(['content' => [], 'settings' => []])
@php
    $t = fn (string $key): ?string => is_array($content[$key] ?? null)
        ? ($content[$key][app()->getLocale()] ?? $content[$key][config('locales.default', 'ar')] ?? null)
        : ($content[$key] ?? null);
@endphp

<div @class([
    'grid gap-8 items-center',
    'lg:grid-cols-2' => filled($content['image'] ?? null),
    'text-center max-w-[42rem] mx-auto' => ($content['align'] ?? 'start') === 'center',
])>
    <div class="min-w-0">
        <h1 class="text-3xl sm:text-4xl font-bold leading-tight mb-4">{{ $t('heading') }}</h1>

        @if($t('subheading'))
            <p class="text-lg text-muted leading-relaxed mb-6">{{ $t('subheading') }}</p>
        @endif

        @if($t('cta_label'))
            <x-ui.button size="lg" :href="$content['cta_url'] ?? '#'">{{ $t('cta_label') }}</x-ui.button>
        @endif
    </div>

    @if(filled($content['image'] ?? null))
        <img src="{{ $content['image'] }}" alt="" class="w-full rounded-lg" loading="lazy">
    @endif
</div>
