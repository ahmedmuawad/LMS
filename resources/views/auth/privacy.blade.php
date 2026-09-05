<x-layouts.student :title="__('بياناتي')" current="privacy">

    <x-ui.page-header :title="__('بياناتي')"
                      :subtitle="__('ما عندنا عنك — تأخذ نسخته أو تطلب حذفه. وهو حقّك لا خدمةٌ نقدّمها.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif
    @error('password')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

    <div class="max-w-[640px] grid gap-5">

        <x-ui.card :title="__('نسخة من بياناتي')">
            <p class="text-muted text-sm leading-relaxed mb-4">
                {{ __('ملفّ يحوي حسابك وتسجيلاتك ودرجاتك وشهاداتك وطلباتك. يُنزَّل فوراً — لا يُرسَل بالبريد ولا تنتظر.') }}
            </p>

            <x-ui.button :href="url('/account/data/export')">{{ __('نزّل بياناتي') }}</x-ui.button>
        </x-ui.card>

        <x-ui.card :title="__('حذف حسابي')">
            <x-ui.alert tone="warning" class="mb-4">
                {{ __('لا رجعة في الحذف: تفقد وصولك إلى كل كورساتك وشهاداتك، ولو كنتَ قد دفعتَ فيها.') }}
            </x-ui.alert>

            <p class="text-muted text-sm leading-relaxed mb-4">
                {{ __('يُمحى اسمك وبريدك وهاتفك وأجهزتك. ويبقى ما يلزم مدرّسك حفظه قانونياً — الطلبات والفواتير والدرجات — بلا اسمك عليه.') }}
            </p>

            <form method="POST" action="{{ url('/account/data') }}" class="grid gap-4">
                @csrf @method('DELETE')

                <x-ui.field :label="__('كلمة المرور')" for="password" required class="mb-0"
                            :hint="__('كلمة المرور هي التأكيد — أصدق من نافذةٍ تسأل «هل أنت متأكد؟».')">
                    <x-ui.input id="password" name="password" type="password" required autocomplete="current-password" />
                </x-ui.field>

                <x-ui.checkbox name="confirm" value="1"
                               :label="__('أفهم أن الحذف لا رجعة فيه.')" />

                <div><x-ui.button type="submit" variant="danger">{{ __('احذف حسابي') }}</x-ui.button></div>
            </form>
        </x-ui.card>

    </div>

</x-layouts.student>
