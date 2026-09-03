@props(['name', 'label', 'hint' => null, 'required' => false, 'value' => null])
@php $v = old($name, $value) ?: '#000000'; @endphp
<x-ui.field :label="$label" :for="'f-'.$name" :hint="$hint" :required="$required" :error="$errors->first($name)"
            x-data="{ color: @js($v) }">
    <div class="flex items-center gap-2">
        <input type="color" x-model="color" :aria-label="__('منتقي اللون')"
               class="size-11 rounded-md border border-line-strong bg-surface p-1 cursor-pointer shrink-0"
               aria-label="{{ __('منتقي لون :field', ['field' => $label]) }}">
        <x-ui.input :id="'f-'.$name" :name="$name" x-model="color" class="font-mono"
                    :invalid="$errors->has($name)" placeholder="#000000" />
    </div>
</x-ui.field>
