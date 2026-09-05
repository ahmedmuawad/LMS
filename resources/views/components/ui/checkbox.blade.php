@props(['label' => null, 'checked' => false, 'type' => 'checkbox'])
{{--
    مربّع اختيار أو زرّ اختيار.

    النوع خاصيّةٌ لا سمة تُمرَّر: كان `type="checkbox"` مكتوباً في
    العنصر، و`type="radio"` يُمرَّر مع بقية السمات — فيخرج العنصر
    بسمتين، ويأخذ المتصفّح الأولى. فكانت أسئلة «اختيار واحد» في
    الامتحان تُعرض مربّعاتٍ يُعلّم الطالب فيها ثلاثاً ولا تصل إلا
    واحدة.
--}}
{{-- مساحة اللمس هي صف التسمية كاملاً لا المربّع وحده --}}
<label class="flex items-center gap-2.5 text-sm cursor-pointer py-1 min-h-11">
    <input type="{{ $type === 'radio' ? 'radio' : 'checkbox' }}" @checked($checked)
           {{ $attributes->merge(['class' => 'size-5 shrink-0 accent-[var(--color-primary)] '
               .($type === 'radio' ? 'rounded-full' : 'rounded-sm')]) }}>
    <span class="min-w-0">{{ $label ?? $slot }}</span>
</label>
