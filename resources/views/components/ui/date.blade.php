@props(['value' => null, 'pattern' => 'd MMMM y', 'time' => false])
{{--
    تاريخٌ بتقويم المشترك وأرقامه.

    مكوّنٌ واحد لا `translatedFormat` في كل قالب: من يريد الهجري
    يريده في كل شاشة، ولا يُعقل أن يُبدَّل في أربعين موضعاً.
--}}
@php
    $formatted = $value === null
        ? ''
        : app(App\Core\Support\Dates::class)->format(
            $value instanceof \Illuminate\Support\Carbon ? $value : \Illuminate\Support\Carbon::parse($value),
            $time ? $pattern.' · HH:mm' : $pattern,
        );
@endphp
@if($formatted !== '')
    <time {{ $attributes }} datetime="{{ $value instanceof \Illuminate\Support\Carbon ? $value->toIso8601String() : $value }}">{{ $formatted }}</time>
@endif
