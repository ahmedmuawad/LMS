@props(['content' => [], 'settings' => []])
@php
    $t = fn (string $key): ?string => is_array($content[$key] ?? null)
        ? ($content[$key][app()->getLocale()] ?? $content[$key][config('locales.default', 'ar')] ?? null)
        : ($content[$key] ?? null);
    $id = $content['video_id'] ?? null;
@endphp

@if($t('heading'))
    <h2 class="text-2xl font-bold mb-6 text-center">{{ $t('heading') }}</h2>
@endif

@if($id)
    <div class="max-w-[900px] mx-auto aspect-video rounded-lg overflow-hidden bg-[#000]">
        @if(($content['provider'] ?? 'youtube') === 'youtube')
            <iframe class="size-full" src="https://www.youtube-nocookie.com/embed/{{ $id }}"
                    title="{{ $t('heading') ?? __('فيديو') }}" loading="lazy"
                    allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen></iframe>
        @elseif(($content['provider'] ?? '') === 'vimeo')
            <iframe class="size-full" src="https://player.vimeo.com/video/{{ $id }}"
                    title="{{ $t('heading') ?? __('فيديو') }}" loading="lazy" allowfullscreen></iframe>
        @else
            <video class="size-full" controls preload="metadata"><source src="{{ $id }}"></video>
        @endif
    </div>
@endif
