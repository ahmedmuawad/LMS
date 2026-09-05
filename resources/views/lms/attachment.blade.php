@php
    $src = url('/attachments/'.$attachment->getKey().'/file');
@endphp

<x-layouts.app :title="$attachment->name()">
<x-site.header />

<main id="main" class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6">

    <div class="flex flex-wrap items-start gap-x-4 gap-y-2 mb-4">
        <div class="min-w-0 flex-1">
            <h1 class="text-lg sm:text-xl font-extrabold truncate">{{ $attachment->name() }}</h1>
            <p class="text-xs text-muted font-mono tabular mt-1">
                {{ $attachment->kindLabel() }} · {{ $attachment->sizeLabel() }}
                @if($lesson) · {{ $lesson->title }} @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2 shrink-0">
            @if($attachment->is_downloadable)
                <x-ui.button size="sm" variant="secondary"
                             :href="$src.'?download=1'">{{ __('تنزيل') }}</x-ui.button>
            @endif
        </div>
    </div>

    @if($attachment->isUnreachable())
        {{-- الصدق أنفع من زرٍّ لا يفعل شيئاً --}}
        <x-ui.card>
            <x-ui.empty :title="__('هذا الملف لا يُعرض في المتصفّح')">
                {{ __('ملفّات Word لا يعرضها المتصفّح، والتنزيل مغلق لهذا الملف. اطلب من مدرّسك إتاحة التنزيل أو رفعه بصيغة PDF.') }}
            </x-ui.empty>
        </x-ui.card>

    @elseif($attachment->isViewable())
        @if($attachment->watermark)
            <x-ui.alert tone="info" class="mb-4">
                {{ __('هذه النسخة موسومة باسمك ورقمك، وكل فتحة مسجّلة. إعادة نشرها تُنسب إليك.') }}
            </x-ui.alert>
        @endif

        {{--
            العارض: إطار الملفّ تحته، وطبقة الوسم فوقه.

            الطبقة `pointer-events-none` فلا تعترض التمرير ولا التكبير
            داخل عارض المتصفّح — الحماية التي تُعطّل القراءة تُلغى
            بأول شكوى.
        --}}
        <div class="relative rounded-lg overflow-hidden border border-line bg-surface-sunken select-none"
             oncontextmenu="return false">

            <iframe src="{{ $src }}#toolbar={{ $attachment->is_downloadable ? 1 : 0 }}&navpanes=0"
                    title="{{ $attachment->name() }}"
                    class="w-full h-[70vh] min-h-[420px] bg-white"
                    loading="lazy"></iframe>

            @if($attachment->watermark)
                <div class="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
                    @for($i = 0; $i < 12; $i++)
                        <span class="absolute whitespace-nowrap font-mono select-none
                                     text-[13px] font-semibold tracking-wide"
                              style="
                                top: {{ 4 + $i * 8 }}%;
                                inset-inline-start: {{ ($i % 3) * 26 + 3 }}%;
                                transform: rotate(-24deg);
                                color: rgba(120,120,120,.28);
                                text-shadow: 0 1px 0 rgba(255,255,255,.35);
                              ">{{ $stamp }}</span>
                    @endfor
                </div>
            @endif
        </div>

        <p class="text-2xs text-subtle mt-3 leading-relaxed">
            {{ __('لا يمنع أي متصفّح تصوير الشاشة منعاً تامّاً. ما نضمنه أن الملف لا يفتحه غير المشتركين، وأن كل نسخة تحمل اسم قارئها، وأن كل فتحة مسجّلة.') }}
        </p>

    @else
        <x-ui.card>
            <x-ui.empty :title="__('لا يُعرض هذا النوع داخل الصفحة')">
                {{ __('نزّل الملف لفتحه بالبرنامج المناسب.') }}
                <x-slot:action>
                    <x-ui.button size="sm" :href="$src.'?download=1'">{{ __('تنزيل') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @endif

    @if($lesson)
        <div class="mt-6">
            <a href="{{ url()->previous() }}"
               class="tap-link text-sm text-primary hover:underline">← {{ __('عودة إلى الدرس') }}</a>
        </div>
    @endif

</main>

<x-site.footer />
</x-layouts.app>
