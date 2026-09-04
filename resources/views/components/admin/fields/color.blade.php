@props(['name', 'label', 'hint' => null, 'required' => false, 'value' => null])
@php $v = old($name, $value) ?: '#000000'; @endphp
{{--
    نطاق Alpine على عنصر عادي داخل الحقل لا على `<x-ui.field>` نفسه:
    Blade لا يصرّف `@js()` داخل خاصية مكوّن، فكان التعبير يخرج حرفياً
    إلى الصفحة ويسقط المنتقي بـ«Invalid or unexpected token» — أي أن
    كل منتقيات ألوان الهوية كانت معطّلة بلا رسالة في الشاشة.
--}}
<x-ui.field :label="$label" :for="'f-'.$name" :hint="$hint" :required="$required" :error="$errors->first($name)">
    <div class="flex items-center gap-2" x-data="{ color: @js($v) }">
        <input type="color" x-model="color"
               class="size-11 rounded-md border border-line-strong bg-surface p-1 cursor-pointer shrink-0"
               aria-label="{{ __('منتقي لون :field', ['field' => $label]) }}">
        <x-ui.input :id="'f-'.$name" :name="$name" x-model="color" class="font-mono"
                    :invalid="$errors->has($name)" placeholder="#000000" />
    </div>
</x-ui.field>
