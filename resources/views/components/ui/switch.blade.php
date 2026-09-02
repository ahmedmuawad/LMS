@props(['label' => null, 'hint' => null, 'checked' => false, 'name' => null])
<label class="flex items-start gap-3 cursor-pointer py-1.5" x-data="{ on: @js((bool) $checked) }">
    <button type="button" role="switch" :aria-checked="on ? 'true' : 'false'" @click="on = !on"
            :class="on ? 'bg-primary' : 'bg-line-strong'"
            class="relative shrink-0 w-11 h-6 rounded-full transition-colors duration-150 mt-0.5">
        <span :class="on ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"
              class="absolute top-0.5 start-0 size-5 rounded-full bg-white shadow-sm transition-transform duration-150"></span>
    </button>
    @if($name)<input type="hidden" :value="on ? 1 : 0" name="{{ $name }}">@endif
    @if($label || $hint)
        <span class="min-w-0">
            @if($label)<span class="block text-sm font-medium">{{ $label }}</span>@endif
            @if($hint)<span class="block text-xs text-subtle mt-0.5">{{ $hint }}</span>@endif
        </span>
    @endif
</label>
