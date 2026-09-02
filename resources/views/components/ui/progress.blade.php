@props(['value' => 0, 'tone' => 'progress', 'label' => null])
@php $v = max(0, min(100, (float) $value)); @endphp
<div {{ $attributes->merge(['class' => 'w-full']) }}>
    <div class="h-1.5 w-full rounded-full bg-surface-sunken overflow-hidden"
         role="progressbar" aria-valuenow="{{ $v }}" aria-valuemin="0" aria-valuemax="100"
         @if($label) aria-label="{{ $label }}" @endif>
        <span class="block h-full rounded-full bg-{{ $tone }}" style="width: {{ $v }}%"></span>
    </div>
</div>
