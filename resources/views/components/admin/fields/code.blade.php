@props(['name', 'label', 'hint' => null, 'required' => false, 'value' => null, 'rows' => 10])
<x-ui.field :label="$label" :for="'f-'.$name" :hint="$hint" :required="$required" :error="$errors->first($name)">
    <x-ui.textarea :id="'f-'.$name" :name="$name" :rows="$rows" :invalid="$errors->has($name)"
                   spellcheck="false" autocapitalize="off" autocorrect="off"
                   class="font-mono text-xs leading-relaxed" dir="ltr">{{ old($name, $value) }}</x-ui.textarea>
</x-ui.field>
