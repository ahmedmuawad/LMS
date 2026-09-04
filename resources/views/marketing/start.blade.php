@php
    $locale = app()->getLocale();

    /*
     | ما يحتاجه المتصفّح ليختار عنك: سعر كل باقة بكل عملة، والأنماط
     | التي تقبلها. فتبديل الدولة أو الباقة يُصحّح ما يُعرض فوراً بلا
     | طلب جديد — والزائر لا يكتشف بعد الإرسال أن اختياره غير متاح.
     */
    $planData = $plans->mapWithKeys(fn ($plan): array => [$plan->key => [
        'name'     => $plan->name[$locale] ?? $plan->name['ar'] ?? $plan->key,
        'trial'    => (int) $plan->trial_days,
        'modes'    => $plan->modes ?: array_keys($modes),
        'prices'   => collect($plan->prices ?? [])
            ->map(fn (int $minor, string $currency): string => \App\Core\Support\Money::fromMinor($minor, $currency)->format($locale))
            ->all(),
    ]])->all();

    $countryData = collect($countries)->map(function (string $value): array {
        [$name, $currency] = explode('|', $value, 2);

        return ['name' => $name, 'currency' => $currency];
    })->all();

    $modeData = collect($modes)->map(fn (array $mode, string $key): array => [
        'name'    => $mode['name'][$locale] ?? $mode['name']['ar'] ?? $key,
        'summary' => $mode['summary'][$locale] ?? $mode['summary']['ar'] ?? '',
        'icon'    => $mode['icon'] ?? '◈',
    ])->all();
@endphp

<x-layouts.marketing :title="__('ابدأ منصّتك')">
    <div class="max-w-[880px] mx-auto px-4 sm:px-6 py-10 sm:py-16"
         x-data="signup({
             plans: @js($planData),
             countries: @js($countryData),
             plan: @js(old('plan', $selected)),
             country: @js(old('country', 'EG')),
             mode: @js(old('mode', 'teacher')),
             slug: @js(old('slug', '')),
             checkUrl: @js(url('/start/slug')),
         })">

        <div class="text-center mb-8">
            <h1 class="text-2xl sm:text-3xl font-extrabold mb-2">{{ __('ابدأ منصّتك') }}</h1>
            <p class="text-sm text-muted leading-relaxed max-w-[52ch] mx-auto">
                {{ __('شاشة واحدة، ثم منصّة عاملة بقاعدة بيانات مستقلة باسمك. باقي التفاصيل تُضبط بالداخل.') }}
            </p>
        </div>

        @if($errors->any())
            <x-ui.alert tone="danger" :title="__('راجع البيانات')" class="mb-5">
                <ul class="grid gap-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form method="POST" action="{{ url('/start') }}" class="grid gap-4">
            @csrf

            {{-- ١) الباقة --}}
            <x-ui.card :title="__('الباقة')" :subtitle="__('تغيّرها لاحقاً من داخل منصّتك بلا فقد بيانات.')">
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($plans as $plan)
                        <label class="cursor-pointer">
                            <input type="radio" name="plan" value="{{ $plan->key }}" x-model="plan" class="peer sr-only">
                            <span class="block h-full p-3 rounded-lg border transition-colors
                                         border-line-strong hover:border-primary
                                         peer-checked:border-primary peer-checked:bg-primary-subtle">
                                <span class="block text-sm font-bold">{{ $plan->name[$locale] ?? $plan->name['ar'] ?? $plan->key }}</span>
                                <span class="block font-mono tabular text-lg mt-1"
                                      x-text="plans[@js($plan->key)].prices[currency] ?? @js(__('حسب الطلب'))"></span>
                                <span class="block text-2xs text-subtle">{{ __('/ شهرياً') }}</span>
                                @if($plan->trial_days > 0)
                                    <span class="block text-2xs text-success font-semibold mt-1">
                                        {{ __(':days يوماً تجربة', ['days' => $plan->trial_days]) }}
                                    </span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </x-ui.card>

            {{-- ٢) النمط: أهمّ اختيار — هو ما يبني اللوحة --}}
            <x-ui.card :title="__('ما الذي تُديره؟')" :subtitle="__('هذا ما يبني قوائم لوحتك — والأنماط غير المتاحة في باقتك تُعطَّل.')">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach($modeData as $key => $mode)
                        <label class="cursor-pointer" x-show="plans[plan].modes.includes(@js($key))">
                            <input type="radio" name="mode" value="{{ $key }}" x-model="mode" class="peer sr-only">
                            <span class="flex items-start gap-3 h-full p-3 rounded-lg border transition-colors
                                         border-line-strong hover:border-primary
                                         peer-checked:border-primary peer-checked:bg-primary-subtle">
                                <span class="text-xl leading-none shrink-0" aria-hidden="true">{{ $mode['icon'] }}</span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold">{{ $mode['name'] }}</span>
                                    <span class="block text-2xs text-subtle leading-relaxed mt-0.5">{{ $mode['summary'] }}</span>
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="grid gap-x-4 sm:grid-cols-2 mt-4">
                    <x-ui.field :label="__('طريقة التقديم')" for="delivery" :error="$errors->first('delivery')">
                        <x-ui.select id="delivery" name="delivery">
                            @foreach($deliveries as $key => $delivery)
                                <option value="{{ $key }}" @selected(old('delivery', 'blended') === $key)>
                                    {{ $delivery['name'][$locale] ?? $delivery['name']['ar'] ?? $key }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>
            </x-ui.card>

            {{-- ٣) المنصّة ونطاقها --}}
            <x-ui.card :title="__('منصّتك')">
                <div class="grid gap-x-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-ui.field :label="__('اسم الأكاديمية أو السنتر')" for="academy" required :error="$errors->first('academy')">
                            <x-ui.input id="academy" name="academy" required :value="old('academy')"
                                        x-on:input="suggestSlug($event.target.value)"
                                        :placeholder="__('أكاديمية الأرقام')" />
                        </x-ui.field>
                    </div>

                    <div class="sm:col-span-2">
                        <x-ui.field :label="__('نطاقك')" for="slug" required :error="$errors->first('slug')"
                                    :hint="__('حروف إنجليزية وأرقام وشرطات. تستطيع ربط نطاقك الخاص لاحقاً.')">
                            <div class="flex items-center gap-0" dir="ltr">
                                <x-ui.input id="slug" name="slug" required dir="ltr" class="rounded-e-none font-mono"
                                            x-model="slug" x-on:input.debounce.400ms="check()"
                                            maxlength="32" placeholder="arqam" />
                                <span class="h-11 grid place-items-center px-3 text-xs font-mono text-muted
                                             border border-s-0 border-line-strong rounded-e-md bg-surface-sunken shrink-0">.{{ $baseDomain }}</span>
                            </div>
                            <p class="text-2xs mt-1.5 min-h-4" x-cloak
                               :class="state === 'taken' ? 'text-danger' : (state === 'free' ? 'text-success' : 'text-subtle')"
                               x-text="message"></p>
                        </x-ui.field>
                    </div>

                    <x-ui.field :label="__('الدولة')" for="country" required :error="$errors->first('country')">
                        <x-ui.select id="country" name="country" x-model="country" required>
                            @foreach($countryData as $code => $country)
                                <option value="{{ $code }}">{{ $country['name'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field :label="__('العملة')" for="currency" :error="$errors->first('currency')"
                                :hint="__('تتبع دولتك — وتُثبَّت على فاتورتك.')">
                        <x-ui.input id="currency" name="currency" readonly dir="ltr" class="font-mono bg-surface-sunken"
                                    x-model="currency" />
                    </x-ui.field>
                </div>
            </x-ui.card>

            {{-- ٤) حسابك --}}
            <x-ui.card :title="__('حسابك')" :subtitle="__('هذا حساب صاحب المنصّة — أعلى صلاحية فيها.')">
                <div class="grid gap-x-4 sm:grid-cols-2">
                    <x-ui.field :label="__('اسمك')" for="name" required :error="$errors->first('name')">
                        <x-ui.input id="name" name="name" required :value="old('name')" />
                    </x-ui.field>

                    <x-ui.field :label="__('الهاتف')" for="phone" :error="$errors->first('phone')">
                        <x-ui.input id="phone" name="phone" type="tel" inputmode="tel" dir="ltr" :value="old('phone')" />
                    </x-ui.field>

                    <div class="sm:col-span-2">
                        <x-ui.field :label="__('البريد')" for="email" required :error="$errors->first('email')"
                                    :hint="__('عليه تصلك الفواتير، وبه تدخل منصّتك.')">
                            <x-ui.input id="email" name="email" type="email" dir="ltr" required :value="old('email')" />
                        </x-ui.field>
                    </div>

                    <x-ui.field :label="__('كلمة المرور')" for="password" required :error="$errors->first('password')">
                        <x-ui.input id="password" name="password" type="password" autocomplete="new-password" required />
                    </x-ui.field>

                    <x-ui.field :label="__('تأكيد كلمة المرور')" for="password_confirmation" required>
                        <x-ui.input id="password_confirmation" name="password_confirmation" type="password"
                                    autocomplete="new-password" required />
                    </x-ui.field>
                </div>

                <x-ui.checkbox name="terms" value="1" :checked="(bool) old('terms')" class="mt-2">
                    {{ __('أوافق على شروط الخدمة وسياسة الخصوصية.') }}
                </x-ui.checkbox>
                @error('terms')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
            </x-ui.card>

            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button type="submit" size="lg" x-bind:disabled="state === 'taken' || state === 'checking'">
                    {{ __('جهّز منصّتي') }}
                </x-ui.button>
                <p class="text-xs text-subtle">
                    {{ __('بلا بطاقة الآن — تختار طريقة الدفع في الخطوة التالية.') }}
                </p>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('signup', (config) => ({
                plans: config.plans,
                countries: config.countries,
                plan: config.plan,
                country: config.country,
                mode: config.mode,
                slug: config.slug,
                state: '',          // '' · checking · free · taken
                message: '',
                touched: false,

                init() {
                    /* باقة لا تدعم النمط المختار: نُصحّح الاختيار بدل أن
                       نرفضه بعد الإرسال. */
                    this.$watch('plan', () => this.fixMode());
                    this.fixMode();
                    if (this.slug) this.check();
                },

                get currency() {
                    return this.countries[this.country]?.currency ?? 'EGP';
                },

                fixMode() {
                    const allowed = this.plans[this.plan]?.modes ?? [];
                    if (allowed.length && ! allowed.includes(this.mode)) this.mode = allowed[0];
                },

                /* اقتراح النطاق من الاسم — ويتوقّف حالما يكتبه المستخدم بنفسه */
                suggestSlug(value) {
                    if (this.touched) return;

                    this.slug = (value || '')
                        .toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .trim()
                        .replace(/\s+/g, '-')
                        .slice(0, 32);

                    this.check();
                },

                async check() {
                    if (document.activeElement?.id === 'slug') this.touched = true;

                    const slug = (this.slug || '').trim();

                    if (slug.length < 3) {
                        this.state = '';
                        this.message = slug === '' ? '' : @js(__('ثلاثة أحرف على الأقل.'));
                        return;
                    }

                    this.state = 'checking';
                    this.message = @js(__('نتحقّق…'));

                    try {
                        const response = await fetch(`${config.checkUrl}?slug=${encodeURIComponent(slug)}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await response.json();

                        this.slug = data.slug;
                        this.state = data.available ? 'free' : 'taken';
                        this.message = data.available
                            ? @js(__('متاح ✓'))
                            : @js(__('محجوز — اختر غيره.'));
                    } catch (e) {
                        this.state = '';
                        this.message = '';
                    }
                },
            }));
        });
    </script>
    @endpush
</x-layouts.marketing>
