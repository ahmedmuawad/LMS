@props(['label' => null, 'checked' => false])
{{-- مساحة اللمس هي صف التسمية كاملاً لا المربّع وحده --}}
<label class="flex items-center gap-2.5 text-sm cursor-pointer py-1">
    <input type="checkbox" @checked($checked)
           {{ $attributes->merge(['class' => 'size-5 shrink-0 accent-[var(--color-primary)] rounded-sm']) }}>
    <span>{{ $label ?? $slot }}</span>
</label>
