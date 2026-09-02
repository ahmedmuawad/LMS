@props(['tabs' => [], 'active' => null])
@php $first = $active ?? array_key_first($tabs); @endphp
<div x-data="{ tab: @js($first) }" {{ $attributes }}>
    <div role="tablist" class="flex gap-1 overflow-x-auto border-b border-line -mb-px">
        @foreach($tabs as $key => $label)
            <button type="button" role="tab" id="tab-{{ $key }}"
                    :aria-selected="tab === @js($key) ? 'true' : 'false'"
                    :tabindex="tab === @js($key) ? 0 : -1"
                    aria-controls="panel-{{ $key }}"
                    @click="tab = @js($key)"
                    @keydown.right.prevent="$el.nextElementSibling?.focus()"
                    @keydown.left.prevent="$el.previousElementSibling?.focus()"
                    :class="tab === @js($key)
                        ? 'text-primary border-primary'
                        : 'text-muted border-transparent hover:text-content'"
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 whitespace-nowrap transition-colors">
                {{ $label }}
            </button>
        @endforeach
    </div>
    <div class="pt-5">{{ $slot }}</div>
</div>
