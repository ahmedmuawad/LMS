<x-layouts.admin :title="__('الواجهة البرمجية')" current="api">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('الواجهة البرمجية')"
                      :subtitle="__('اربط منصّتك بنظام مدرستك أو ببرنامج محاسبتك.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    {{--
        المفتاح يُعرض مرة واحدة ولا يُخزَّن نصّاً — فيُقال ذلك
        صراحةً قبل أن يغادر الصفحة، لا بعد أن يبحث عنه.
    --}}
    @if(session('plain_token'))
        <x-ui.alert tone="warning" :title="__('انسخه الآن')" class="mb-5">
            <p class="text-sm mb-3">{{ __('هذه المرة الوحيدة التي يُعرض فيها — لا نحفظه نصّاً عندنا.') }}</p>

            <div x-data="{ copied: false }" class="flex flex-wrap items-center gap-2">
                <code class="min-w-0 flex-1 text-xs font-mono break-all bg-surface-sunken rounded-md px-3 py-2"
                      x-ref="tok">{{ session('plain_token') }}</code>
                <x-ui.button type="button" size="sm" variant="secondary"
                             x-on:click="navigator.clipboard.writeText($refs.tok.textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-text="copied ? '{{ __('نُسخ') }}' : '{{ __('انسخ') }}'"></span>
                </x-ui.button>
            </div>
        </x-ui.alert>
    @endif

    <x-ui.card :title="__('مفتاح جديد')" class="mb-6">
        <form method="POST" action="{{ route('admin.api.store') }}" class="grid gap-4">
            @csrf

            <x-ui.field :label="__('اسم المفتاح')" for="name" required class="mb-0"
                        :hint="__('لتعرف أيّ نظامٍ يستعمله حين تريد إلغاءه.')">
                <x-ui.input id="name" name="name" required maxlength="100"
                            :placeholder="__('مثال: نظام المدرسة')" />
            </x-ui.field>

            <fieldset>
                <legend class="text-sm font-semibold mb-2">{{ __('الصلاحيات') }}</legend>
                <p class="text-xs text-muted leading-relaxed mb-3">
                    {{ __('امنح أقلّ ما يكفي: مفتاحٌ يكتب بالخطأ يُفسد بياناتك، ومفتاحٌ يقرأ لا يُفسد شيئاً.') }}
                </p>

                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach($scopes as $key => $label)
                        <label class="flex items-center gap-2.5 min-h-11 px-3 rounded-lg border border-line-strong text-sm cursor-pointer">
                            <input type="checkbox" name="scopes[]" value="{{ $key }}"
                                   class="accent-[var(--sem-primary)]"
                                   @checked(str_ends_with($key, ':read'))>
                            <span class="min-w-0">
                                {{ __($label) }}
                                @if(str_ends_with($key, ':write'))
                                    <span class="text-2xs text-warning">· {{ __('كتابة') }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <x-ui.field :label="__('ينتهي في')" for="exp" class="mb-0"
                        :hint="__('اتركه فارغاً لمفتاح لا ينتهي.')">
                <x-ui.input id="exp" name="expires_at" type="date" />
            </x-ui.field>

            <div><x-ui.button type="submit">{{ __('أنشئ المفتاح') }}</x-ui.button></div>
        </form>
    </x-ui.card>

    @if($tokens->isEmpty())
        <x-ui.card class="mb-6">
            <x-ui.empty :title="__('لا مفاتيح')">
                {{ __('أنشئ مفتاحاً لتربط منصّتك بنظامٍ آخر.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-2 mb-6">
            @foreach($tokens as $token)
                <div class="surface-card p-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">{{ $token->name }}</p>
                        <p class="text-2xs text-subtle font-mono mt-0.5">{{ $token->masked() }}</p>
                        <p class="text-2xs text-muted mt-1">
                            {{ implode(' · ', array_map(fn ($s) => __($scopes[$s] ?? $s), (array) $token->scopes)) }}
                        </p>
                        <p class="text-2xs text-subtle font-mono tabular mt-1">
                            {{ $token->last_used_at
                                ? __('آخر استعمال :when', ['when' => $token->last_used_at->diffForHumans()])
                                : __('لم يُستعمل بعد') }}
                            @if($token->expires_at)
                                · {{ __('ينتهي :date', ['date' => $token->expires_at->translatedFormat('j M Y')]) }}
                            @endif
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.api.destroy', $token->id) }}"
                          onsubmit="return confirm('{{ __('إلغاء هذا المفتاح؟ أي تكاملٍ يستعمله سيتوقّف فوراً.') }}')">
                        @csrf @method('DELETE')
                        <x-ui.button type="submit" size="sm" variant="danger">{{ __('إلغاء') }}</x-ui.button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    <x-ui.card :title="__('كيف تستعملها')">
        <p class="text-sm text-muted leading-relaxed mb-3">
            {{ __('أرسل المفتاح في ترويسة Authorization مع كل طلب:') }}
        </p>

        <pre class="text-2xs font-mono bg-surface-sunken rounded-md p-3 overflow-x-auto" dir="ltr">curl -H "Authorization: Bearer usos_..." {{ url('/api/v1/students?per_page=50') }}</pre>

        <p class="text-sm font-semibold mt-5 mb-2">{{ __('النقاط المتاحة') }}</p>
        <ul class="grid gap-1 text-2xs font-mono" dir="ltr">
            @foreach([
                'GET  /api/v1/me',
                'GET  /api/v1/courses',
                'GET  /api/v1/students',
                'GET  /api/v1/groups',
                'GET  /api/v1/enrollments',
                'POST /api/v1/enrollments',
                'GET  /api/v1/attendance?from=&to=',
                'GET  /api/v1/invoices?status=unpaid',
            ] as $line)
                <li class="text-muted">{{ $line }}</li>
            @endforeach
        </ul>

        <p class="text-2xs text-subtle leading-relaxed mt-4">
            {{ __('كل نقطة تُحرَس بصلاحيّتها، والحدّ ١٢٠ طلباً في الدقيقة لكل مفتاح — على المفتاح لا على عنوان الشبكة، فالتكاملات تتشارك العناوين.') }}
        </p>
    </x-ui.card>

</div>
</x-layouts.admin>
