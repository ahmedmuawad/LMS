@props([
    'name',
    'label',
    'hint' => null,
    'required' => false,
    'value' => null,
    'type' => 'date',
    'min' => null,
    'max' => null,
])
<x-ui.field :label="$label" :for="'f-'.$name" :hint="$hint" :required="$required" :error="$errors->first($name)">
    {{-- التقويم يُفتح بالنقر على الحقل كلّه لا على الأيقونة وحدها: مساحة اللمس على الموبايل --}}
    <x-ui.input :id="'f-'.$name" :name="$name" :type="$type" :min="$min" :max="$max"
                :value="old($name, $value)" :invalid="$errors->has($name)"
                class="[&::-webkit-calendar-picker-indicator]:opacity-60
                       [&::-webkit-calendar-picker-indicator]:cursor-pointer
                       [&::-webkit-calendar-picker-indicator]:dark:invert"
                onclick="this.showPicker && this.showPicker()" />
</x-ui.field>
