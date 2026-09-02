@props(['invalid' => false])
<input {{ $attributes->merge(['class' =>
    'w-full bg-surface text-content text-sm rounded-md border px-3 py-2.5 placeholder:text-subtle
     transition-[border-color,box-shadow] duration-150
     focus:outline-none focus:border-primary focus:ring-[3px] focus:ring-primary-subtle
     disabled:opacity-60 '.($invalid ? 'border-danger' : 'border-line-strong')]) }}
    @if($invalid) aria-invalid="true" @endif>
