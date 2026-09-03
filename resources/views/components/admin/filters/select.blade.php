@props(['name', 'label', 'options' => [], 'placeholder' => null, 'value' => null])
<label class="flex flex-col gap-1.5 min-w-40">
    <span class="text-xs font-semibold text-muted">{{ $label }}</span>
    <x-ui.select :name="$name" onchange="this.form.requestSubmit()">
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $key => $text)
            <option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ $text }}</option>
        @endforeach
    </x-ui.select>
</label>
