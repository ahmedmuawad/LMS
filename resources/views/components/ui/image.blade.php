@props([
    'src' => null,
    'path' => null,
    'alt' => '',
    'width' => null,
    'height' => null,
    'sizes' => '100vw',
    'eager' => false,
])
{{--
    صورةٌ بمقاسات وصيغٍ متعدّدة.

    ## لماذا مكوّن لا وسم

    غلافُ الكورس يُرفع بعرض ٣٠٠٠ بكسل ويُعرض في ٣٦٠ على الهاتف؛
    وإرسالُ الأصل يأكل ميجابايتين من باقة طالب. والمتصفّح يختار
    المقاس والصيغة وحده — إن أعطيناه الخيارات.

    ## وwidth وheight ليسا زينة

    بلاهما تقفز الصفحة حين تصل الصورة، فيضغط القارئ زرّاً غير
    الذي قصده. وهما ما يحجز المكان قبل الوصول.

    ## وما لا يُعرَف مساره لا يُشتقّ منه

    شعارٌ من `public/` أو صورةٌ من رابطٍ خارجي تُعرض كما هي: لا
    قرصَ عندنا يقرؤها المحوّل.
--}}
@php
    /*
     | المسار يُشتقّ من الرابط إن لم يُمرَّر.
     |
     | حقول الصور تخزّن رابطاً كاملاً لا مساراً، وكلُّ الشاشات تمرّره
     | كما هو. فاشتقاقُ المسار هنا يجعل المكوّن بديلاً مباشراً لكل
     | `<img>` قائم — بلا تعديل ما خُزّن ولا لمس عشر شاشات.
     |
     | ولا يُشتقّ من رابطٍ ليس لنا: صورةٌ على خادمٍ آخر لا قرصَ
     | عندنا يقرؤها المحوّل.
     */
    $base = rtrim((string) Illuminate\Support\Facades\Storage::disk('public')->url(''), '/');

    if ($path === null && is_string($src) && $base !== '' && str_starts_with($src, $base)) {
        $path = ltrim(mb_substr($src, mb_strlen($base)), '/');
    }

    $useVariants = $path !== null
        && (bool) setting('performance.responsive_images', true)
        && tenant() !== null;

    $formats = $useVariants ? app(App\Core\Media\ImageVariants::class)->formats() : [];

    $widths = App\Core\Media\ImageVariants::WIDTHS;

    $srcset = function (string $format) use ($path, $widths): string {
        return implode(', ', array_map(
            fn (int $w): string => route('media.variant', [
                'width' => $w, 'format' => $format, 'path' => $path,
            ]).' '.$w.'w',
            $widths,
        ));
    };

    /*
     | التحميل الكسول لكل صورة إلا الأولى.
     |
     | صورةٌ فوق الطيّة مؤجَّلة تصل متأخّرةً فتتأخّر أهمّ ما في
     | الشاشة — وهو ما يقيسه LCP. فالغلاف الأول يُطلب فوراً.
     */
    $lazy = ! $eager && (bool) setting('performance.lazy_load', true);
@endphp

@if($src === null && $path === null)
    {{-- لا صورة: لا وسمٌ فارغ يترك إطاراً مكسوراً --}}
@elseif($formats === [])
    <img src="{{ $src ?? Illuminate\Support\Facades\Storage::url($path) }}"
         alt="{{ $alt }}"
         @if($width) width="{{ $width }}" @endif
         @if($height) height="{{ $height }}" @endif
         loading="{{ $lazy ? 'lazy' : 'eager' }}"
         decoding="async"
         @if($eager) fetchpriority="high" @endif
         {{ $attributes }}>
@else
    <picture>
        @foreach($formats as $format)
            <source type="image/{{ $format }}" srcset="{{ $srcset($format) }}" sizes="{{ $sizes }}">
        @endforeach

        {{-- والأصل آخرُ سطرٍ في الدفاع: متصفّحٌ لا يعرف أيّ صيغة يراه --}}
        <img src="{{ $src ?? Illuminate\Support\Facades\Storage::url($path) }}"
             alt="{{ $alt }}"
             @if($width) width="{{ $width }}" @endif
             @if($height) height="{{ $height }}" @endif
             loading="{{ $lazy ? 'lazy' : 'eager' }}"
             decoding="async"
             @if($eager) fetchpriority="high" @endif
             {{ $attributes }}>
    </picture>
@endif
