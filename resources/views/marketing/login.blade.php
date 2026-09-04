@props([])
@php
    $notice ??= null;
    $choices ??= [];
    $email ??= '';
@endphp

<x-layouts.marketing :title="__('ادخل إلى منصّتك')">
    <div class="max-w-[440px] mx-auto px-4 sm:px-6 py-16 sm:py-24">

        <h1 class="text-2xl font-extrabold mb-2">{{ __('ادخل إلى منصّتك') }}</h1>
        <p class="text-sm text-muted leading-relaxed mb-6">
            {{-- نقول لماذا نسأل: بريدٌ يُطلب بلا سبب يبدو تصيّداً --}}
            {{ __('اكتب بريدك ونأخذك إلى نطاق منصّتك — كلمة مرورك تُكتب هناك لا هنا.') }}
        </p>

        @if($notice)
            <x-ui.alert tone="info" class="mb-5">{{ $notice }}</x-ui.alert>
        @endif

        @if($choices !== [])
            <x-ui.card :title="__('لك أكثر من منصّة')" :subtitle="__('اختر التي تريد الدخول إليها.')" class="mb-5">
                <ul class="grid gap-2">
                    @foreach($choices as $choice)
                        <li>
                            <a href="{{ $choice['url'] }}"
                               class="flex items-center justify-between gap-3 min-h-12 px-3 rounded-md border border-line-strong
                                      hover:border-primary hover:bg-primary-subtle transition-colors">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold truncate">{{ $choice['name'] }}</span>
                                    <span class="block text-2xs text-subtle font-mono" dir="ltr">{{ $choice['domain'] }}</span>
                                </span>
                                <span class="text-primary shrink-0 flip-rtl" aria-hidden="true">←</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <x-ui.card>
            <form method="POST" action="{{ url('/login') }}" class="grid gap-4">
                @csrf
                <x-ui.field :label="__('بريدك')" for="email" required :error="$errors->first('email')">
                    <x-ui.input id="email" name="email" type="email" dir="ltr" required autofocus
                                :value="old('email', $email)" placeholder="you@example.com" />
                </x-ui.field>

                <x-ui.button type="submit" class="w-full">{{ __('تابع') }}</x-ui.button>
            </form>
        </x-ui.card>

        <p class="text-xs text-subtle text-center mt-5">
            {{ __('ليست لك منصّة بعد؟') }}
            <a href="{{ url('/#pricing') }}" class="tap-link text-primary font-semibold hover:underline">{{ __('اختر باقتك') }}</a>
        </p>
    </div>
</x-layouts.marketing>
