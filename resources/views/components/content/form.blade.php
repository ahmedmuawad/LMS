@props(['form'])
<form method="POST" action="{{ url('/forms/'.$form->key) }}" class="surface-card p-5 sm:p-6">
    @csrf

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @error('form')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    @foreach($form->fields ?? [] as $field)
        @php
            $name = 'data['.($field['name'] ?? '').']';
            $id = 'f-'.$form->key.'-'.($field['name'] ?? '');
            $error = $errors->first('data.'.($field['name'] ?? ''));
            $label = is_array($field['label'] ?? null)
                ? ($field['label'][app()->getLocale()] ?? reset($field['label']))
                : ($field['label'] ?? $field['name'] ?? '');
        @endphp

        <x-ui.field :label="$label" :for="$id" :required="(bool) ($field['required'] ?? false)"
                    :hint="$field['hint'] ?? null" :error="$error">
            @if(($field['type'] ?? 'text') === 'textarea')
                <x-ui.textarea :name="$name" :id="$id" rows="5" :invalid="filled($error)">{{ old('data.'.($field['name'] ?? '')) }}</x-ui.textarea>
            @elseif(($field['type'] ?? '') === 'select')
                <x-ui.select :name="$name" :id="$id" :invalid="filled($error)">
                    <option value="">{{ __('اختر…') }}</option>
                    @foreach(($field['options'] ?? []) as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </x-ui.select>
            @elseif(($field['type'] ?? '') === 'checkbox')
                <x-ui.checkbox :name="$name" :id="$id" value="1" :label="$field['text'] ?? $label" />
            @else
                <x-ui.input :name="$name" :id="$id" :type="$field['type'] ?? 'text'"
                            :value="old('data.'.($field['name'] ?? ''))" :invalid="filled($error)" />
            @endif
        </x-ui.field>
    @endforeach

    <x-ui.button type="submit">{{ __('إرسال') }}</x-ui.button>
</form>
