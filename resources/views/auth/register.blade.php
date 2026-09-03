<x-layouts.app :title="__('إنشاء حساب')">
<x-auth.shell :title="__('أنشئ حسابك')" :subtitle="__('دقيقة واحدة، ثم تبدأ التعلّم.')">

    <form method="POST" action="{{ url('/register') }}">
        @csrf

        <x-ui.field :label="__('الاسم')" for="name" :required="true" :error="$errors->first('name')">
            <x-ui.input id="name" name="name" autocomplete="name" autofocus
                        :value="old('name')" :invalid="$errors->has('name')" />
        </x-ui.field>

        <x-ui.field :label="__('البريد الإلكتروني')" for="email" :required="true" :error="$errors->first('email')">
            <x-ui.input id="email" name="email" type="email" autocomplete="email"
                        :value="old('email')" :invalid="$errors->has('email')" />
        </x-ui.field>

        <x-ui.field :label="__('الهاتف')" for="phone"
                    :required="(bool) setting('users.verify_phone', false)"
                    :hint="__('لتذكيرك بالمواعيد وإشعارات واتساب.')"
                    :error="$errors->first('phone')">
            <x-ui.input id="phone" name="phone" type="tel" autocomplete="tel" dir="ltr"
                        :value="old('phone')" :invalid="$errors->has('phone')" placeholder="01xxxxxxxxx" />
        </x-ui.field>

        <x-ui.field :label="__('كلمة المرور')" for="password" :required="true"
                    :hint="$passwordHint" :error="$errors->first('password')">
            <x-ui.input id="password" name="password" type="password" autocomplete="new-password"
                        :invalid="$errors->has('password')" />
        </x-ui.field>

        <x-ui.field :label="__('تأكيد كلمة المرور')" for="password_confirmation" :required="true">
            <x-ui.input id="password_confirmation" name="password_confirmation" type="password"
                        autocomplete="new-password" />
        </x-ui.field>

        @if($mayBeInstructor)
            <fieldset class="mb-4">
                <legend class="text-sm font-semibold mb-2">{{ __('أنا هنا لكي…') }}</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    <x-ui.radio-card name="role" value="student" :label="__('أتعلّم')"
                                     :hint="__('أشترك في الكورسات وأتابع تقدّمي.')"
                                     :checked="old('role', 'student') === 'student'" />
                    <x-ui.radio-card name="role" value="instructor" :label="__('أُدرّس')"
                                     :hint="__('أنشر كورساتي وأبيعها بعمولة.')"
                                     :checked="old('role') === 'instructor'" />
                </div>
            </fieldset>
        @endif

        @if(setting('users.terms_required', true))
            <div class="mb-5">
                <x-ui.checkbox name="terms" value="1" :checked="old('terms')">
                    <span class="text-sm">
                        {{ __('أوافق على') }}
                        <a href="{{ url('/terms') }}" target="_blank" rel="noopener" class="text-primary hover:underline inline-flex min-h-9 items-center">{{ __('الشروط والأحكام') }}</a>
                        {{ __('و') }}
                        <a href="{{ url('/privacy') }}" target="_blank" rel="noopener" class="text-primary hover:underline inline-flex min-h-9 items-center">{{ __('سياسة الخصوصية') }}</a>
                    </span>
                </x-ui.checkbox>
                @error('terms')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
            </div>
        @endif

        <x-ui.button type="submit" size="lg" class="w-full justify-center">{{ __('أنشئ الحساب') }}</x-ui.button>
    </form>

    <x-slot:footer>
        {{ __('لديك حساب؟') }}
        <a href="{{ url('/login') }}" class="text-primary font-semibold hover:underline">{{ __('سجّل دخولك') }}</a>
    </x-slot:footer>
</x-auth.shell>
</x-layouts.app>
