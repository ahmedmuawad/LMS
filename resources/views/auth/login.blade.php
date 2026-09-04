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
        </x-ui.card>
    </div>
</div>
</x-layouts.app>
