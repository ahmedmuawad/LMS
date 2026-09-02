@props(['size' => 'md'])
@php $sizes = ['sm' => 'size-4 border-2', 'md' => 'size-6 border-2', 'lg' => 'size-9 border-[3px]']; @endphp
<span {{ $attributes->merge(['class' => 'inline-block rounded-full border-current border-t-transparent animate-spin text-primary '.($sizes[$size] ?? $sizes['md'])]) }}
      role="status" aria-label="{{ __('جارٍ التحميل') }}"></span>
