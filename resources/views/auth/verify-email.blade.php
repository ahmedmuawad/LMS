<x-layouts.app :title="__('تأكيد البريد')">
<x-auth.shell :title="__('أكّد بريدك')"
              :subtitle="__('أرسلنا رابطاً إلى :email — اضغطه لتفعيل حسابك.', ['email' => auth()->user()->email])">

    <div class="text-sm text-muted leading-relaxed mb-5">
        {{ __('لم يصل شيء؟ تفقّد مجلّد البريد المزعج، أو أعد الإرسال.') }}
    </div>

    @if($errors->has('email'))
        <x-ui.alert tone="warning" class="mb-4">{{ $errors->first('email') }}</x-ui.alert>
    @endif

    <div class="flex flex-col gap-2">
        <form method="POST" action="{{ url('/verify-email/resend') }}">
            @csrf
            <x-ui.button type="submit" size="lg" class="w-full justify-center">{{ __('أعد إرسال الرابط') }}</x-ui.button>
        </form>

        <form method="POST" action="{{ url('/logout') }}">
            @csrf
            <x-ui.button type="submit" variant="ghost" class="w-full justify-center">{{ __('تسجيل الخروج') }}</x-ui.button>
        </form>
    </div>
</x-auth.shell>
</x-layouts.app>
