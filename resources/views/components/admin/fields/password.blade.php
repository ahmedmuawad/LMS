@props(['name', 'label', 'hint' => null, 'required' => false, 'value' => null, 'isSet' => false])
{{--
    نصّ الزرّ يأتي من نطاق Alpine لا من `@js()` داخل خاصية مكوّن:
    Blade لا يصرّف `@js()` هناك، فكان `x-text` يخرج بالتعبير حرفياً
    ويسقط الزرّ — فلا يُظهر السرّ ولا يُخفيه.
--}}
<x-ui.field :label="$label" :for="'f-'.$name"
            :hint="$hint ?? ($isSet ? __('محفوظ ومشفّر. اترك الحقل فارغاً للإبقاء عليه.') : null)"
            :required="$required" :error="$errors->first($name)">
    <div class="flex items-center gap-2"
         x-data="{ shown: false, labels: { on: @js(__('إخفاء')), off: @js(__('إظهار')) } }">
        <x-ui.input :id="'f-'.$name" :name="$name" x-bind:type="shown ? 'text' : 'password'"
                    type="password" autocomplete="new-password" value=""
                    :placeholder="$isSet ? '••••••••••••' : ''" :invalid="$errors->has($name)" class="font-mono" />
        <x-ui.button type="button" variant="secondary" size="sm" x-on:click="shown = ! shown"
                     x-text="shown ? labels.on : labels.off">{{ __('إظهار') }}</x-ui.button>
    </div>
</x-ui.field>
