<x-layouts.app :title="__('التوثيق بخطوتين')">
<x-auth.shell :title="__('رمز التحقّق')"
              :subtitle="__('اكتب الرمز من تطبيق المصادقة، أو أحد رموز الاستعادة.')">

    <form method="POST" action="{{ url('/two-factor') }}">
        @csrf

        <x-ui.field :label="__('الرمز')" for="code" :required="true" :error="$errors->first('code')">
            <x-ui.input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus
                        dir="ltr" class="text-center font-mono text-lg tracking-[0.3em]"
                        maxlength="20" :invalid="$errors->has('code')" />
        </x-ui.field>

        <x-ui.button type="submit" size="lg" class="w-full justify-center">{{ __('تحقّق') }}</x-ui.button>
    </form>

    <x-slot:footer>
        <form method="POST" action="{{ url('/logout') }}" class="inline">
            @csrf
            <button type="submit" class="text-primary font-semibold hover:underline">{{ __('دخول بحساب آخر') }}</button>
        </form>
    </x-slot:footer>
</x-auth.shell>
</x-layouts.app>
