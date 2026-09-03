@props(['name', 'label', 'hint' => null, 'required' => false, 'value' => null, 'options' => [], 'placeholder' => null])
<x-ui.field :label="$label" :for="'f-'.$name" :hint="$hint" :required="$required" :error="$errors->first($name)">
    <x-ui.select :id="'f-'.$name" :name="$name" :invalid="$errors->has($name)">
        @if($placeholder || ! $required)<option value="">{{ $placeholder ?? __('اختر…') }}</option>@endif
        @foreach($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </x-ui.select>
</x-ui.field>
