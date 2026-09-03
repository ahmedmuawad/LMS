@props(['title', 'subtitle' => null, 'back' => null])
<header {{ $attributes->merge(['class' => 'flex flex-wrap items-start justify-between gap-4 mb-6 min-w-0']) }}>
    <div class="min-w-0 flex-1">
        @isset($breadcrumb)<div class="mb-2">{{ $breadcrumb }}</div>@endisset
        <div class="flex items-center gap-2 min-w-0">
            @if($back)
                <a href="{{ $back }}" class="size-8 grid place-items-center rounded-md text-muted hover:bg-surface-sunken hover:text-content transition-colors shrink-0"
                   aria-label="{{ __('رجوع') }}"><span class="flip-rtl" aria-hidden="true">←</span></a>
            @endif
            <h1 class="text-xl sm:text-2xl font-bold truncate min-w-0">{{ $title }}</h1>
        </div>
        @if($subtitle)<p class="text-sm text-muted mt-1">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)<div class="flex items-center gap-2 flex-wrap shrink-0">{{ $actions }}</div>@endisset
</header>
