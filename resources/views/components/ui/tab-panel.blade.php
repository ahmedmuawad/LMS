@props(['name'])
<div x-show="tab === @js($name)" x-cloak role="tabpanel" id="panel-{{ $name }}" aria-labelledby="tab-{{ $name }}" {{ $attributes }}>
    {{ $slot }}
</div>
