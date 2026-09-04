@props(['name', 'label', 'hint' => null, 'value' => [], 'categories' => []])
@php
    $pool = (array) ($value ?? []);
    $levels = [
        'easy' => ['label' => __('سهل'), 'tone' => 'success'],
        'medium' => ['label' => __('متوسط'), 'tone' => 'info'],
        'hard' => ['label' => __('صعب'), 'tone' => 'warning'],
    ];
@endphp

<fieldset class="mb-4" x-data="{
        counts: {
            easy: {{ (int) old($name.'.easy', $pool['easy'] ?? 0) }},
            medium: {{ (int) old($name.'.medium', $pool['medium'] ?? 0) }},
            hard: {{ (int) old($name.'.hard', $pool['hard'] ?? 0) }},
        },
        get total() { return this.counts.easy + this.counts.medium + this.counts.hard; },
    }">
    <legend class="text-sm font-semibold mb-2">{{ $label }}</legend>

    <div class="grid gap-3 sm:grid-cols-3">
        @foreach($levels as $key => $level)
            <label class="block">
                <span class="flex items-center gap-1.5 text-xs font-semibold text-muted mb-1.5">
                    <span class="size-2 rounded-full bg-{{ $level['tone'] }}" aria-hidden="true"></span>
                    {{ $level['label'] }}
                </span>
                <input type="number" min="0" max="200" x-model.number="counts.{{ $key }}"
                       name="{{ $name }}[{{ $key }}]"
                       class="w-full h-11 px-3 rounded-md border border-line-strong bg-surface text-sm font-mono tabular
                              focus:outline-none focus:ring-2 focus:ring-primary">
            </label>
        @endforeach
    </div>

    @if($categories !== [])
        <label class="block mt-3">
            <span class="text-xs font-semibold text-muted mb-1.5 block">{{ __('من تصنيف') }}</span>
            <x-ui.select :name="$name.'[category_id]'">
                <option value="">{{ __('كل التصنيفات') }}</option>
                @foreach($categories as $id => $text)
                    <option value="{{ $id }}" @selected((string) old($name.'.category_id', $pool['category_id'] ?? '') === (string) $id)>{{ $text }}</option>
                @endforeach
            </x-ui.select>
        </label>
    @endif

    {{-- المجموع يُحسب أمام عين من يبني الورقة، لا يُكتشف بعد تسليمها --}}
    <p class="text-xs text-subtle mt-2">
        {{ $hint ?? __('يُسحب عشوائياً من البنك، فلا يرى طالبان الورقة نفسها.') }}
        <span class="font-mono tabular ms-1">{{ __('المجموع:') }} <span x-text="total"></span></span>
    </p>
    @error($name)<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
</fieldset>
