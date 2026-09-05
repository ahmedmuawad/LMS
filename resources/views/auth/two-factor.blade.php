<x-layouts.student :title="__('التوثيق بخطوتين')" current="security">

<div>

    <x-ui.page-header :title="__('التوثيق بخطوتين')"
                      :subtitle="__('طبقة ثانية: كلمة المرور وحدها لا تكفي لفتح حسابك.')"
                      :back="url('/account')" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if($forced && ! $enabled)
        <x-ui.alert tone="warning" :title="__('إلزامي في هذا الموقع')" class="mb-4">
            {{ __('فعّله لمتابعة الاستخدام.') }}
        </x-ui.alert>
    @endif

    @if($enabled)
        <div class="surface-card p-5">
            <div class="flex items-start gap-3 mb-5">
                <span class="size-10 shrink-0 rounded-md grid place-items-center bg-success-subtle text-success" aria-hidden="true">✓</span>
                <div>
                    <p class="font-semibold">{{ __('مفعّل') }}</p>
                    <p class="text-sm text-muted mt-0.5">{{ __('يُطلب الرمز عند كل دخول جديد.') }}</p>
                </div>
            </div>

            <h2 class="font-bold text-sm mb-2">{{ __('رموز الاستعادة') }}</h2>
            <p class="text-2xs text-subtle leading-relaxed mb-3">
                {{ __('كل رمز يعمل مرّة واحدة. احفظها بعيداً عن هاتفك — فهي طريقك الوحيد إن فقدته.') }}
            </p>

            <ul class="grid gap-2 grid-cols-2 sm:grid-cols-4 mb-5">
                @foreach($recoveryCodes as $code)
                    <li class="font-mono text-xs bg-surface-sunken border border-line rounded-md px-2 py-2 text-center" dir="ltr">{{ $code }}</li>
                @endforeach
            </ul>

            @unless($forced)
                <form method="POST" action="{{ url('/account/two-factor') }}"
                      class="pt-4 border-t border-default flex flex-col sm:flex-row items-stretch sm:items-end gap-2">
                    @csrf
                    @method('DELETE')

                    <x-ui.field :label="__('كلمة المرور للتأكيد')" for="password" class="mb-0 flex-1"
                                :error="$errors->first('password')">
                        <x-ui.input id="password" name="password" type="password" autocomplete="current-password" />
                    </x-ui.field>

                    <x-ui.button type="submit" variant="danger" class="h-11 w-full sm:w-auto sm:shrink-0">
                        {{ __('أطفئ التوثيق') }}
                    </x-ui.button>
                </form>
            @endunless
        </div>
    @else
        <div class="surface-card p-5">
            <ol class="flex flex-col gap-5">
                <li>
                    <p class="font-semibold text-sm mb-2">١ · {{ __('امسح الرمز بتطبيق مصادقة') }}</p>
                    <p class="text-2xs text-subtle mb-3">{{ __('Google Authenticator · Microsoft Authenticator · 1Password — أيّها شئت.') }}</p>
                    <div class="inline-block bg-white p-3 rounded-lg border border-line">{!! $qr !!}</div>
                </li>

                <li>
                    <p class="font-semibold text-sm mb-2">٢ · {{ __('أو اكتب المفتاح يدوياً') }}</p>
                    <code class="font-mono text-xs bg-surface-sunken border border-line rounded-md px-3 py-2 inline-block break-all" dir="ltr">{{ $secret }}</code>
                </li>

                <li>
                    <p class="font-semibold text-sm mb-2">٣ · {{ __('اكتب الرمز الظاهر في التطبيق') }}</p>

                    <form method="POST" action="{{ url('/account/two-factor') }}" class="flex flex-col sm:flex-row items-stretch sm:items-end gap-2 max-w-sm">
                        @csrf

                        <x-ui.field :label="__('الرمز')" for="code" class="mb-0 flex-1" :error="$errors->first('code')">
                            <x-ui.input id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                                        dir="ltr" class="text-center font-mono tracking-[0.3em]" maxlength="8" />
                        </x-ui.field>

                        <x-ui.button type="submit" class="h-11 w-full sm:w-auto sm:shrink-0">{{ __('فعّل') }}</x-ui.button>
                    </form>
                </li>
            </ol>
        </div>
    @endif

    {{--
        الأجهزة تحت التوثيق: كلاهما جواب «كيف أحمي حسابي؟»، وفصلهما
        شاشتين يجعل صاحب الحساب يجد نصف الجواب ويظنّه كلّه.
    --}}
    <section class="mt-10 pt-8 border-t border-line">
        <h2 class="text-base font-bold mb-1">{{ __('الأجهزة الداخلة إلى حسابك') }}</h2>
        <p class="text-sm text-muted leading-relaxed mb-5">
            @if($deviceLimit)
                {{ __('حسابك يسمح بـ :n جهاز. افصل ما لم تعد تستعمله ليتّسع لغيره.', ['n' => $deviceLimit]) }}
            @else
                {{ __('هذه الأجهزة التي دخلت إلى حسابك. افصل أيّ جهاز لا تعرفه.') }}
            @endif
        </p>

        @if($devices->isEmpty())
            <p class="text-sm text-subtle">{{ __('لا أجهزة مسجّلة بعد.') }}</p>
        @else
            <div class="grid gap-2">
                @foreach($devices as $device)
                    <div class="surface-card p-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">{{ $device->label ?: __('جهاز') }}</p>
                            <p class="text-2xs text-subtle font-mono tabular mt-0.5">
                                {{ __('آخر دخول') }}: {{ $device->last_seen_at?->diffForHumans() ?? '—' }}
                                @if($device->last_ip) · {{ $device->last_ip }} @endif
                            </p>
                        </div>

                        <form method="POST" action="{{ route('account.devices.destroy', $device->getKey()) }}"
                              onsubmit="return confirm('{{ __('فصل هذا الجهاز؟') }}')">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" size="sm" variant="secondary">{{ __('افصل') }}</x-ui.button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>

</x-layouts.student>
