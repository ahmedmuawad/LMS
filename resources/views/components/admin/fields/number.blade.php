@props(['name', 'label', 'hint' => null, 'required' => false, 'value' => null, 'min' => null, 'max' => null, 'suffix' => null])
<x-ui.field :label="$label" :for="'f-'.$name" :hint="$hint" :required="$required" :error="$errors->first($name)">
    <div class="relative">
        <x-ui.input :id="'f-'.$name" :name="$name" type="number" inputmode="decimal" step="any"
                    :min="$min" :max="$max" :value="old($name, $value)" :invalid="$errors->has($name)"
                    :class="$suffix ? 'pe-16' : ''" />
        @if($suffix)
            <span class="absolute inset-y-0 end-3 grid place-items-center text-xs text-subtle pointer-events-none font-mono">{{ $suffix }}</span>
        @endif
    </div>
</x-ui.field>
