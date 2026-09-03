<x-layouts.app :title="__('دخول فريق المنصة')">
<div class="min-h-screen grid place-items-center p-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <div class="size-12 rounded-xl grid place-items-center text-primary-on font-bold text-xl mx-auto mb-3"
                 style="background-color: var(--sem-primary-hover); background-image: linear-gradient(140deg, var(--color-primary), var(--sem-primary-hover));"
                 aria-hidden="true">أ</div>
            <h1 class="text-xl font-bold">{{ __('إدارة المنصة') }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('لفريق المنصة فقط') }}</p>
        </div>

        <x-ui.card>
            <form method="POST" action="{{ url('/super/login') }}">
                @csrf
                <x-ui.field :label="__('البريد الإلكتروني')" for="email" :required="true" :error="$errors->first('email')">
                    <x-ui.input id="email" name="email" type="email" autocomplete="username" autofocus
                                :value="old('email')" :invalid="$errors->has('email')" />
                </x-ui.field>
                <x-ui.field :label="__('كلمة المرور')" for="password" :required="true" :error="$errors->first('password')">
                    <x-ui.input id="password" name="password" type="password" autocomplete="current-password"
                                :invalid="$errors->has('password')" />
                </x-ui.field>
                <div class="mb-5"><x-ui.checkbox name="remember" :label="__('تذكّرني')" /></div>
                <x-ui.button type="submit" size="lg" class="w-full justify-center">{{ __('دخول') }}</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</div>
</x-layouts.app>
