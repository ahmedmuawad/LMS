<x-layouts.app :title="__('حسابي')">
<x-site.header />

<main id="main" class="max-w-[760px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('حسابي')" :subtitle="$user->email" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    {{-- ---------- البيانات ---------- --}}
    <section class="surface-card p-5 mb-5">
        <h2 class="font-bold mb-4">{{ __('بياناتك') }}</h2>

        <form method="POST" action="{{ url('/account') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="flex items-center gap-4 mb-5">
                <x-ui.avatar :name="$user->name" :src="$user->avatar_path" size="lg" />
                <div class="min-w-0 flex-1">
                    <x-ui.field :label="__('الصورة')" for="avatar" class="mb-0" :error="$errors->first('avatar')">
                        <input type="file" name="avatar" id="avatar" accept="image/*"
                               class="block w-full text-sm text-muted file:me-3 file:py-2 file:px-4 file:rounded-md
                                      file:border-0 file:text-sm file:font-semibold file:bg-primary-subtle file:text-primary">
                    </x-ui.field>
                </div>
            </div>

            <x-ui.field :label="__('الاسم')" for="name" :required="true" :error="$errors->first('name')">
                <x-ui.input id="name" name="name" :value="old('name', $user->name)" :invalid="$errors->has('name')" />
            </x-ui.field>

            <x-ui.field :label="__('البريد الإلكتروني')" for="email"
                        :hint="__('تغييره يحتاج تأكيد العنوان الجديد — راسلنا لتغييره.')">
                <x-ui.input id="email" name="email_display" :value="$user->email" readonly class="opacity-60" />
            </x-ui.field>

            <x-ui.field :label="__('الهاتف')" for="phone" :error="$errors->first('phone')">
                <x-ui.input id="phone" name="phone" type="tel" dir="ltr"
                            :value="old('phone', $user->phone)" :invalid="$errors->has('phone')" />
            </x-ui.field>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :label="__('لغة الواجهة')" for="locale" :error="$errors->first('locale')">
                    <x-ui.select id="locale" name="locale">
                        @foreach($locales as $code => $meta)
                            <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $meta['native'] ?? $code }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field :label="__('المنطقة الزمنية')" for="timezone" :error="$errors->first('timezone')">
                    <x-ui.input id="timezone" name="timezone" dir="ltr"
                                :value="old('timezone', $user->timezone ?? tenant('timezone'))" />
                </x-ui.field>
            </div>

            <x-ui.button type="submit">{{ __('حفظ') }}</x-ui.button>
        </form>
    </section>

    {{-- ---------- الأمان ---------- --}}
    <section class="surface-card p-5 mb-5">
        <h2 class="font-bold mb-4">{{ __('كلمة المرور') }}</h2>

        <form method="POST" action="{{ url('/account/password') }}">
            @csrf
            @method('PUT')

            <x-ui.field :label="__('كلمة المرور الحالية')" for="current_password" :required="true"
                        :error="$errors->first('current_password')">
                <x-ui.input id="current_password" name="current_password" type="password"
                            autocomplete="current-password" :invalid="$errors->has('current_password')" />
            </x-ui.field>

            <x-ui.field :label="__('كلمة المرور الجديدة')" for="password" :required="true"
                        :hint="$passwordHint" :error="$errors->first('password')">
                <x-ui.input id="password" name="password" type="password" autocomplete="new-password"
                            :invalid="$errors->has('password')" />
            </x-ui.field>

            <x-ui.field :label="__('تأكيد كلمة المرور')" for="password_confirmation" :required="true">
                <x-ui.input id="password_confirmation" name="password_confirmation" type="password"
                            autocomplete="new-password" />
            </x-ui.field>

            <x-ui.button type="submit">{{ __('غيّر كلمة المرور') }}</x-ui.button>
        </form>
    </section>

    <section class="surface-card p-5 mb-5 flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-0">
            <h2 class="font-bold">{{ __('التوثيق بخطوتين') }}</h2>
            <p class="text-sm text-muted mt-1">
                {{ $twoFactorEnabled
                    ? __('مفعّل — يُطلب رمز عند كل دخول جديد.')
                    : __('غير مفعّل. كلمة المرور وحدها تحمي حسابك الآن.') }}
            </p>
        </div>

        <x-ui.button as="a" :href="url('/account/two-factor')"
                     :variant="$twoFactorEnabled ? 'secondary' : 'primary'"
                     class="w-full sm:w-auto sm:shrink-0">
            {{ $twoFactorEnabled ? __('إدارة') : __('فعّله') }}
        </x-ui.button>
    </section>

    <section class="surface-card p-5 mb-5 flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-0">
            <h2 class="font-bold">{{ __('الإشعارات') }}</h2>
            <p class="text-sm text-muted mt-1">{{ __('اختر ما يصلك وعلى أي قناة.') }}</p>
        </div>
        <x-ui.button as="a" :href="url('/account/notifications')" variant="secondary"
                     class="w-full sm:w-auto sm:shrink-0">{{ __('التفضيلات') }}</x-ui.button>
    </section>

    {{-- ---------- حذف الحساب ---------- --}}
    @if($mayDelete)
        <section class="surface-card p-5 border-danger" x-data="{ open: false }">
            <h2 class="font-bold text-danger mb-1">{{ __('حذف الحساب') }}</h2>
            <p class="text-sm text-muted leading-relaxed mb-4">
                {{ __('يُغلق حسابك ولا يُمحى سجلّك المالي: الفواتير تُحفظ سنوات بحكم القانون، ومحوُها يترك فاتورة بلا صاحب.') }}
            </p>

            <x-ui.button type="button" variant="danger" x-show="! open" @click="open = true">{{ __('أريد حذف حسابي') }}</x-ui.button>

            <form method="POST" action="{{ url('/account') }}" x-show="open" x-cloak
                  class="flex flex-col sm:flex-row items-stretch sm:items-end gap-2">
                @csrf
                @method('DELETE')

                <x-ui.field :label="__('اكتب كلمة المرور للتأكيد')" for="delete_password" class="mb-0 flex-1"
                            :error="$errors->first('password')">
                    <x-ui.input id="delete_password" name="password" type="password" autocomplete="current-password" />
                </x-ui.field>

                <x-ui.button type="submit" variant="danger" class="h-11 w-full sm:w-auto sm:shrink-0">{{ __('احذف نهائياً') }}</x-ui.button>
                <x-ui.button type="button" variant="ghost" @click="open = false" class="h-11">{{ __('تراجع') }}</x-ui.button>
            </form>
        </section>
    @endif
</main>

<x-site.footer />
</x-layouts.app>
