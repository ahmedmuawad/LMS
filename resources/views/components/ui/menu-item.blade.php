@props(['href' => null, 'icon' => null, 'danger' => false])
<{{ $href ? 'a' : 'button' }}
    {{ $attributes->merge(['class' =>
        'w-full flex items-center gap-2.5 px-3 py-2 text-sm rounded-md text-start transition-colors '
        .($danger ? 'text-danger hover:bg-danger-subtle' : 'text-content hover:bg-surface-sunken')]
        + ($href ? ['href' => $href] : ['type' => 'button'])) }}
    role="menuitem">
    @if($icon)<span aria-hidden="true" class="shrink-0">{{ $icon }}</span>@endif
    <span class="truncate">{{ $slot }}</span>
</{{ $href ? 'a' : 'button' }}>
