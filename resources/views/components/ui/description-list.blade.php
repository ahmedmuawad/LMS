@props(['items' => []])
<dl {{ $attributes->merge(['class' => 'divide-y divide-[var(--color-line)]']) }}>
    @foreach($items as $label => $value)
        <div class="flex items-start justify-between gap-4 py-2.5 text-sm">
            <dt class="text-muted shrink-0">{{ $label }}</dt>
            <dd class="text-end font-medium min-w-0 break-words">{{ $value }}</dd>
        </div>
    @endforeach
</dl>
