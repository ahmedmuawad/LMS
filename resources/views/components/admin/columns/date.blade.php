@props(['iso' => null, 'display' => null])
@if($display)
    <time datetime="{{ $iso }}" class="font-mono text-xs tabular whitespace-nowrap">{{ $display }}</time>
@else
    <span class="text-subtle">—</span>
@endif
