@props(['content' => [], 'settings' => []])
@php
    $t = fn (string $key): ?string => is_array($content[$key] ?? null)
        ? ($content[$key][app()->getLocale()] ?? $content[$key][config('locales.default', 'ar')] ?? null)
        : ($content[$key] ?? null);
    $tone = $content['tone'] ?? 'primary';
@endphp

<div @class([
    'rounded-lg p-8 sm:p-10 text-center',
    'bg-primary text-primary-on' => $tone === 'primary',
    'bg-accent text-accent-on' => $tone === 'accent',
    'surface-card' => $tone === 'surface',
])>
    <h2 class="text-2xl sm:text-3xl font-bold mb-3">{{ $t('heading') }}</h2>

    @if($t('body'))
        <p @class(['mb-6 leading-relaxed max-w-[44ch] mx-auto', 'opacity-90' => $tone !== 'surface', 'text-muted' => $tone === 'surface'])>{{ $t('body') }}</p>
    @endif

    @if($t('button_label'))
        <x-ui.button size="lg" :variant="$tone === 'surface' ? 'primary' : 'secondary'"
                     :href="$content['button_url'] ?? '#'">{{ $t('button_label') }}</x-ui.button>
    @endif
</div>
