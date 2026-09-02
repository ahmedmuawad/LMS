@props(['items' => []])
<ol {{ $attributes->merge(['class' => 'relative']) }}>
    @foreach($items as $i => $item)
        <li class="relative flex gap-4 pb-5 last:pb-0">
            @if($i < count($items) - 1)
                <span class="absolute top-7 start-3 bottom-0 w-px bg-line -translate-x-1/2 rtl:translate-x-1/2" aria-hidden="true"></span>
            @endif
            <span @class([
                'relative size-6 rounded-full grid place-items-center text-2xs shrink-0 font-mono',
                'bg-success-subtle text-success' => ($item['tone'] ?? '') === 'success',
                'bg-danger-subtle text-danger'   => ($item['tone'] ?? '') === 'danger',
                'bg-primary-subtle text-primary' => ! in_array($item['tone'] ?? '', ['success', 'danger'], true),
            ]) aria-hidden="true">{{ $item['icon'] ?? '•' }}</span>
            <div class="min-w-0 -mt-0.5">
                <p class="text-sm font-medium">{{ $item['title'] }}</p>
                @isset($item['meta'])<p class="text-xs text-subtle mt-0.5 font-mono">{{ $item['meta'] }}</p>@endisset
                @isset($item['body'])<p class="text-sm text-muted mt-1">{{ $item['body'] }}</p>@endisset
            </div>
        </li>
    @endforeach
</ol>
