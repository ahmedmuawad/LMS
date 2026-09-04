@props(['state' => 'no'])
@php
    /*
     | علامة خلية المقارنة. النص المرئي رمز، والمعنى في نصّ مخفي
     | للقارئ الصوتي: وثيقة 13 تمنع نقل معلومة باللون أو الشكل وحده.
     */
    $marks = [
        'yes'     => ['glyph' => '●', 'class' => 'text-success', 'label' => 'متوفر'],
        'partial' => ['glyph' => '◐', 'class' => 'text-warning', 'label' => 'جزئي أو بباقة أعلى'],
        'no'      => ['glyph' => '○', 'class' => 'text-locked', 'label' => 'غير متوفر'],
        'soon'    => ['glyph' => '◔', 'class' => 'text-info', 'label' => 'معلن قريباً'],
    ];
    $mark = $marks[$state] ?? $marks['no'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center text-base leading-none '.$mark['class']]) }}>
    <span aria-hidden="true">{{ $mark['glyph'] }}</span>
    <span class="sr-only">{{ __($mark['label']) }}</span>
</span>
