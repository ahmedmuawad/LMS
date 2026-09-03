@props(['name', 'label', 'hint' => null, 'required' => false, 'value' => null, 'rows' => 4, 'placeholder' => null])
<x-ui.field :label="$label" :for="'f-'.$name" :hint="$hint" :required="$required" :error="$errors->first($name)">
    <x-ui.textarea :id="'f-'.$name" :name="$name" :rows="$rows" :placeholder="$placeholder"
                   :invalid="$errors->has($name)">{{ old($name, $value) }}</x-ui.textarea>
</x-ui.field>
