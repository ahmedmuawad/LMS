@props(['name', 'label', 'hint' => null, 'value' => null])
<div class="mb-4">
    <input type="hidden" name="{{ $name }}" value="0">
    <x-ui.switch :name="$name" :label="$label" :hint="$hint" :checked="(bool) old($name, $value)" />
    @error($name)<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
</div>
