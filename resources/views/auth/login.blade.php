<x-layouts.app :title="__('تسجيل الدخول')">
<div class="min-h-screen grid place-items-center p-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <div class="size-12 rounded-xl grid place-items-center text-primary-on font-bold text-xl mx-auto mb-3"
                 style="background-color: var(--sem-primary-hover); background-image: linear-gradient(140deg, var(--color-primary), var(--sem-primary-hover));"
                 aria-hidden="true">{{ mb_substr(site_name(), 0, 1) }}</div>
            <h1 class="text-xl font-bold">{{ site_name() }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('سجّل الدخول للمتابعة') }}</p>
        </div>

        <x-ui.card>
            @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    {{--
        من بلغ حدّ أجهزته يجد مخرجاً هنا.
        شاشة فكّ الأجهزة خلف الدخول، فالمقفول لا يصلها — ويبقى
        محبوساً خارج حسابه بلا باب إلا الدعم.
    --}}
    @if(session('device_limit'))
        <x-ui.alert tone="warning" :title="__('بلغتَ حدّ الأجهزة')" class="mb-4">
            <p class="text-sm mb-3">{{ session('device_limit') }}</p>

            <form method="POST" action="{{ url('/login/release-device') }}"
                  x-data
                  @submit="$el.password.value = document.getElementById('password')?.value ?? ''">
                @csrf
                <input type="hidden" name="email" value="{{ old('email') }}">
                <input type="hidden" name="password" value="">
                <x-ui.button type="submit" size="sm" variant="secondary">
                    {{ __('افصل أقدم جهاز وادخل') }}
                </x-ui.button>
            </form>

            <p class="text-2xs text-muted mt-2 leading-relaxed">
                {{ __('اكتب كلمة مرورك أعلاه ثم اضغط الزرّ — العدد لا يزيد، والأقدم هو الذي يخرج.') }}
            </p>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ url('/login') }}">
                @csrf

                <x-ui.field :label="__('البريد الإلكتروني أو الهاتف')" for="email" :required="true"
                            :error="$errors->first('email')">
                    <x-ui.input id="email" name="email" type="text" autocomplete="username" autofocus
                                :value="old('email')" :invalid="$errors->has('email')" />
                </x-ui.field>

                <x-ui.field :label="__('كلمة المرور')" for="password" :required="true"
                            :error="$errors->first('password')">
                    <x-ui.input id="password" name="password" type="password" autocomplete="current-password"
                                :invalid="$errors->has('password')" />
                </x-ui.field>

                <div class="flex items-center justify-between gap-3 mb-5">
                    <x-ui.checkbox name="remember" :label="__('تذكّرني')" />
                    <a href="{{ url('/forgot-password') }}" class="text-xs text-primary hover:underline">{{ __('نسيت كلمة المرور؟') }}</a>
                </div>

                <x-ui.button type="submit" size="lg" class="w-full justify-center">{{ __('دخول') }}</x-ui.button>
            </form>

            @php
                $passkeys = app(App\Core\Auth\Passkeys::class)->enabled();
                $networks = app(App\Core\Auth\SocialLogin::class)->available();
            @endphp

            @if($passkeys || $networks !== [])
                <div class="flex items-center gap-3 my-5" aria-hidden="true">
                    <span class="h-px flex-1 bg-line"></span>
                    <span class="text-2xs text-subtle">{{ __('أو') }}</span>
                    <span class="h-px flex-1 bg-line"></span>
                </div>
            @endif

            @if($passkeys)
                {{--
                    زرُّ المفتاح يظهر حيث يعمل فقط.

                    متصفّحٌ قديم يرى زرّاً لا يفعل شيئاً حين يُضغَط —
                    وهذا أسوأ من غيابه: يظنّ المستخدم أن حسابه معطوب.
                    فيُخفى في Blade ويُظهره جافاسكربت بعد أن يتأكّد.
                --}}
                <div x-data="passkeyLogin()" x-init="check()" x-show="supported" x-cloak class="mb-3">
                    <x-ui.button type="button" variant="secondary" size="lg"
                                 class="w-full justify-center gap-2"
                                 x-on:click="go()" x-bind:disabled="busy">
                        <span aria-hidden="true">⚿</span>
                        <span x-text="busy ? '{{ __('جارٍ…') }}' : '{{ __('الدخول بمفتاح المرور') }}'"></span>
                    </x-ui.button>

                    <p class="text-2xs text-danger mt-2" x-show="error" x-text="error" x-cloak></p>
                </div>
            @endif

            @foreach($networks as $key => $label)
                <a href="{{ route('social.redirect', ['provider' => $key]) }}"
                   class="flex items-center justify-center gap-2 w-full min-h-11 mb-2 rounded-lg border border-line-strong text-sm font-semibold hover:border-primary transition-colors">
                    {{ __('المتابعة بـ:net', ['net' => $label]) }}
                </a>
            @endforeach
        </x-ui.card>
    </div>
</div>

@if(app(App\Core\Auth\Passkeys::class)->enabled())
    @push('scripts')
    <script>
        window.usosPasskeys = {
            loginOptionsUrl: @js(route('passkey.options')),
            loginUrl: @js(route('passkey.login')),
        };

        /*
         | الزرّ يُظهره جافاسكربت بعد أن يتأكّد من دعم المتصفّح.
         |
         | زرٌّ لا يفعل شيئاً حين يُضغَط أسوأ من غيابه: يظنّ المستخدم
         | أن حسابه معطوب فيتصل بالدعم.
         */
        function passkeyLogin() {
            return {
                supported: false, busy: false, error: '',

                check() {
                    this.supported = window.usosPasskeysApi?.supported() ?? false;
                },

                async go() {
                    this.busy = true;
                    this.error = '';

                    try {
                        await window.usosPasskeysApi.login();
                    } catch (e) {
                        this.error = e.message;
                        this.busy = false;
                    }
                },
            };
        }
    </script>
    @endpush
@endif
</x-layouts.app>
