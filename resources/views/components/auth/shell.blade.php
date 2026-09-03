@props(['title', 'subtitle' => null, 'width' => 'max-w-sm'])
{{--
    غلاف واحد لكل شاشات المصادقة.

    كانت كل شاشة تُعيد بناء الترويسة والشعار؛ وسبعُ شاشات بسبع نسخ
    تعني أن تغيير الشعار يُنسى في إحداها.
--}}
<div class="min-h-screen grid place-items-center p-4">
    <div class="w-full {{ $width }}">
        <div class="text-center mb-6">
            <a href="{{ url('/') }}" class="size-12 rounded-xl grid place-items-center text-primary-on font-bold text-xl mx-auto mb-3"
               style="background-color: var(--sem-primary-hover); background-image: linear-gradient(140deg, var(--color-primary), var(--sem-primary-hover));"
               aria-label="{{ __('الرئيسية') }}">{{ mb_substr((string) (setting()->translated('general.site_name') ?: tenant('name') ?? 'أ'), 0, 1) }}</a>
            <h1 class="text-xl font-bold">{{ $title }}</h1>
            @if($subtitle)<p class="text-sm text-muted mt-1 leading-relaxed">{{ $subtitle }}</p>@endif
        </div>

        @if(session('status'))
            <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card>{{ $slot }}</x-ui.card>

        {{-- الروابط هنا أهداف لمس: سطر نصّ بارتفاع ١٧px يفشل بوابة اللمس --}}
        @isset($footer)
            <p class="flex flex-wrap items-center justify-center gap-x-1 text-xs text-muted mt-3
                      [&_a]:inline-flex [&_a]:min-h-11 [&_a]:items-center [&_a]:px-1">{{ $footer }}</p>
        @endisset
    </div>
</div>
