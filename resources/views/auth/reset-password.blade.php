<x-layouts.app :title="__('كلمة مرور جديدة')">
<x-auth.shell :title="__('كلمة مرور جديدة')" :subtitle="__('اخترها قوية، ولن نسألك عنها مرة أخرى.')">

    <form method="POST" action="{{ url('/reset-password') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-ui.field :label="__('البريد الإلكتروني')" for="email" :required="true" :error="$errors->first('email')">
            <x-ui.input id="email" name="email" type="email" autocomplete="email"
                        :value="old('email', $email)" :invalid="$errors->has('email')" />
        </x-ui.field>

        <x-ui.field :label="__('كلمة المرور الجديدة')" for="password" :required="true"
                    :hint="$passwordHint" :error="$errors->first('password')">
            <x-ui.input id="password" name="password" type="password" autocomplete="new-password" autofocus
                        :invalid="$errors->has('password')" />
        </x-ui.field>

        <x-ui.field :label="__('تأكيد كلمة المرور')" for="password_confirmation" :required="true">
            <x-ui.input id="password_confirmation" name="password_confirmation" type="password"
                        autocomplete="new-password" />
        </x-ui.field>

        <x-ui.button type="submit" size="lg" class="w-full justify-center">{{ __('غيّر كلمة المرور') }}</x-ui.button>
    </form>
</x-auth.shell>
</x-layouts.app>
