@props(['name', 'label', 'hint' => null, 'required' => false, 'value' => null, 'isSet' => false])
<x-ui.field :label="$label" :for="'f-'.$name"
            :hint="$hint ?? ($isSet ? __('محفوظ ومشفّر. اترك الحقل فارغاً للإبقاء عليه.') : null)"
            :required="$required" :error="$errors->first($name)"
            x-data="{ shown: false }">
    <div class="flex items-center gap-2">
        <x-ui.input :id="'f-'.$name" :name="$name" x-bind:type="shown ? 'text' : 'password'"
                    type="password" autocomplete="new-password" value=""
                    :placeholder="$isSet ? '••••••••••••' : ''" :invalid="$errors->has($name)" class="font-mono" />
        <x-ui.button type="button" variant="secondary" size="sm" @click="shown = !shown"
                     x-text="shown ? @js(__('إخفاء')) : @js(__('إظهار'))">{{ __('إظهار') }}</x-ui.button>
    </div>
</x-ui.field>
