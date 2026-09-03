@props(['name', 'label', 'hint' => null, 'required' => false, 'value' => null, 'type' => 'text', 'placeholder' => null])
<x-ui.field :label="$label" :for="'f-'.$name" :hint="$hint" :required="$required" :error="$errors->first($name)">
    <x-ui.input :id="'f-'.$name" :name="$name" :type="$type" :placeholder="$placeholder"
                :value="old($name, $value)" :invalid="$errors->has($name)"
                :aria-describedby="$errors->has($name) ? 'f-'.$name.'-error' : ($hint ? 'f-'.$name.'-hint' : null)" />
</x-ui.field>
