@props(['value' => null, 'description' => null, 'limit' => null, 'mono' => false])
<div class="min-w-0">
    <div @class(['truncate', 'font-mono tabular' => $mono])>
        {{ $limit ? Str::limit((string) $value, $limit) : ($value ?? '—') }}
    </div>
    @if($description)<div class="text-xs text-subtle truncate mt-0.5">{{ $description }}</div>@endif
</div>
