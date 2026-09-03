@props(['service'])
<article class="surface-card overflow-hidden flex flex-col">
    <a href="{{ url('/services/'.$service->slug) }}" class="block aspect-[16/9] bg-surface-sunken relative overflow-hidden">
        @if($service->cover)
            <img src="{{ $service->cover->url() }}" alt="{{ $service->cover->alt ?? '' }}" class="size-full object-cover" loading="lazy">
        @else
            <span class="absolute inset-0 grid place-items-center text-3xl text-subtle" aria-hidden="true">◇</span>
        @endif
    </a>

    <div class="p-4 flex flex-col gap-2 flex-1 min-w-0">
        <h3 class="font-bold leading-snug">
            <a href="{{ url('/services/'.$service->slug) }}" class="tap-link hover:text-primary transition-colors">{{ $service->title }}</a>
        </h3>

        @if($service->excerpt)
            <p class="text-sm text-muted leading-relaxed line-clamp-2">{{ $service->excerpt }}</p>
        @endif

        <div class="flex items-center justify-between gap-2 mt-auto pt-2">
            <span class="font-mono text-sm tabular">
                {{ $service->needsQuote() ? __('بعرض سعر') : $service->price()->format() }}
            </span>
            @if($service->type === 'appointment')
                <span class="text-2xs text-subtle">
                    {{ trans_choice('{1} دقيقة|{2} دقيقتان|[3,10] :count دقائق|[11,*] :count دقيقة', (int) $service->duration_minutes, ['count' => (int) $service->duration_minutes]) }}
                </span>
            @endif
        </div>
    </div>
</article>
