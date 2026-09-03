<x-layouts.app :title="__('استعادة كلمة المرور')">
<x-auth.shell :title="__('نسيت كلمة المرور؟')"
              :subtitle="__('اكتب بريدك ونرسل إليك رابط تعيين كلمة مرور جديدة.')">

    <form method="POST" action="{{ url('/forgot-password') }}">
        @csrf

        <x-ui.field :label="__('البريد الإلكتروني')" for="email" :required="true" :error="$errors->first('email')">
            <x-ui.input id="email" name="email" type="email" autocomplete="email" autofocus
                        :value="old('email')" :invalid="$errors->has('email')" />
        </x-ui.field>

        <x-ui.button type="submit" size="lg" class="w-full justify-center">{{ __('أرسل الرابط') }}</x-ui.button>
    </form>

    <x-slot:footer>
        <a href="{{ url('/login') }}" class="text-primary font-semibold hover:underline">{{ __('رجوع إلى الدخول') }}</a>
    </x-slot:footer>
</x-auth.shell>
</x-layouts.app>
