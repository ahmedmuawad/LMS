@props(['title', 'summary' => null])
@php
    $effective = \Illuminate\Support\Carbon::parse(config('legal.effective_from'));
@endphp

<x-layouts.marketing :title="$title" :description="$summary">
    {{--
        عمود واحد ضيّق: النصّ القانوني يُقرأ سطراً سطراً، والسطر
        الطويل يجعل العين تفقد مكانها فيُتخطّى ما لا يجوز تخطّيه.
    --}}
    <article class="max-w-[68ch] mx-auto px-4 sm:px-6 py-12 sm:py-16">

        <h1 class="text-2xl sm:text-3xl font-extrabold mb-3">{{ $title }}</h1>

        @if($summary)
            <p class="text-sm text-muted leading-relaxed mb-4">{{ $summary }}</p>
        @endif

        <p class="text-2xs text-subtle font-mono mb-8 pb-6 border-b border-line">
            {{ __('سارية من :date', ['date' => $effective->translatedFormat('j F Y')]) }}
            @if(config('legal.entity.name'))
                · {{ config('legal.entity.name') }}
            @endif
        </p>

        {{--
            الأنماط هنا لا في كل صفحة: ثلاث وثائق بثلاث تنسيقات تبدو
            ثلاث جهات. والمقاسات أكبر قليلاً من المعتاد لأن ما يُقرأ
            مرة واحدة بانتباه يستحقّ راحة العين.
        --}}
        <div class="grid gap-6
                    [&_h2]:text-lg [&_h2]:font-bold [&_h2]:mt-4 [&_h2]:scroll-mt-24
                    [&_h3]:text-base [&_h3]:font-semibold [&_h3]:mt-2
                    [&_p]:text-sm [&_p]:leading-loose [&_p]:text-muted
                    [&_li]:text-sm [&_li]:leading-loose [&_li]:text-muted
                    [&_ul]:grid [&_ul]:gap-2 [&_ul]:ps-5 [&_ul]:list-disc
                    [&_ol]:grid [&_ol]:gap-2 [&_ol]:ps-5 [&_ol]:list-decimal
                    [&_strong]:text-content [&_strong]:font-semibold
                    {{-- الرابط داخل النصّ هدف لمس: سطرٌ بارتفاع ٢١px يفشل بوابة اللمس،
                         والهامش السالب يمنع تمدّد الفقرة حوله --}}
                    [&_a]:text-primary [&_a]:underline
                    [&_a]:inline-block [&_a]:py-2 [&_a]:-my-2">
            {{ $slot }}
        </div>

        <div class="mt-10 pt-6 border-t border-line">
            <p class="text-sm font-semibold mb-1">{{ __('أسئلة عن هذه الوثيقة؟') }}</p>
            <p class="text-xs text-muted leading-relaxed">
                {{ __('راسلنا على') }}
                <a href="mailto:{{ config('legal.entity.email') }}"
                   class="tap-link text-primary font-mono break-all hover:underline">{{ config('legal.entity.email') }}</a>
                @if(config('legal.entity.phone'))
                    · {{ config('legal.entity.phone') }}
                @endif
            </p>

            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-4 text-xs">
                <a href="{{ url('/terms') }}" class="tap-link text-muted hover:text-content">{{ __('شروط الخدمة') }}</a>
                <a href="{{ url('/privacy') }}" class="tap-link text-muted hover:text-content">{{ __('سياسة الخصوصية') }}</a>
                <a href="{{ url('/refund') }}" class="tap-link text-muted hover:text-content">{{ __('سياسة الاسترداد') }}</a>
                <a href="{{ url('/') }}" class="tap-link text-muted hover:text-content">{{ __('الرئيسية') }}</a>
            </div>
        </div>
    </article>
</x-layouts.marketing>
