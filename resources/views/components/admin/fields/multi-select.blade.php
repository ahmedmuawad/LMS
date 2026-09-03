@props(['name', 'label', 'hint' => null, 'value' => [], 'options' => [], 'columns' => 2])
@php $selected = (array) old($name, $value ?? []); @endphp
<fieldset class="mb-4">
    <legend class="text-sm font-semibold mb-2">{{ $label }}</legend>
    <input type="hidden" name="{{ $name }}[]" value="">
    <div class="grid gap-2 {{ $columns >= 3 ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2' }}">
        @foreach($options as $key => $text)
            <x-ui.checkbox :name="$name.'[]'" :value="$key" :label="$text"
                           :id="'f-'.$name.'-'.$key" :checked="in_array((string) $key, array_map('strval', $selected), true)" />
        @endforeach
    </div>
    @if($hint)<p class="text-xs text-subtle mt-2">{{ $hint }}</p>@endif
    @error($name)<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
</fieldset>
