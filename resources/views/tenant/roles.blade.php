<x-layouts.admin :title="__('الأدوار والصلاحيات')" current="roles">
<div class="max-w-[1000px]">

    <x-ui.page-header :title="__('الأدوار والصلاحيات')"
                      :subtitle="__('من يملك ماذا في منصّتك — عدّله كما يناسب فريقك.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    {{--
        صاحب المنصّة لا يُعرض.

        ومشتركٌ ينزع بالخطأ صلاحيةً من صاحب المنصّة يُقفل نفسه خارج
        لوحته، ولا بابَ إلا نحن. فقولُ ذلك أوضح من إخفائه بلا سبب.
    --}}
    <x-ui.alert tone="info" class="mb-5">
        <p class="text-sm leading-relaxed">
            {{ __('صاحب المنصّة يملك كل شيء دائماً ولا يظهر هنا — كي لا يُقفل أحدٌ نفسه خارج لوحته.') }}
        </p>
    </x-ui.alert>

    @foreach($roles as $role => $data)
        <x-ui.card :title="$data['label']" class="mb-5">
            <x-slot:actions>
                @if($data['abilities'] !== $data['default'])
                    <x-ui.badge tone="warning">{{ __('معدَّل') }}</x-ui.badge>

                    <form method="POST" action="{{ route('admin.roles.reset', $role) }}" class="inline"
                          onsubmit="return confirm('{{ __('إعادة هذا الدور إلى التوزيع الافتراضي؟') }}')">
                        @csrf
                        <x-ui.button type="submit" size="sm" variant="ghost">{{ __('أعد الافتراضي') }}</x-ui.button>
                    </form>
                @endif
            </x-slot:actions>

            <form method="POST" action="{{ route('admin.roles.update', $role) }}"
                  x-data="{ count: {{ count($data['abilities']) }} }">
                @csrf @method('PUT')

                <div class="grid gap-4">
                    @foreach($groups as $group => $abilities)
                        <div>
                            <p class="text-2xs font-semibold text-subtle mb-1.5 font-mono" dir="ltr">{{ $group }}</p>

                            <div class="grid gap-1.5 sm:grid-cols-2">
                                @foreach($abilities as $ability)
                                    <label class="flex items-start gap-2.5 min-h-10 px-3 py-2 rounded-lg border border-line text-xs cursor-pointer">
                                        <input type="checkbox" name="abilities[]" value="{{ $ability }}"
                                               class="mt-0.5 accent-[var(--sem-primary)]"
                                               x-on:change="count += $event.target.checked ? 1 : -1"
                                               @checked(in_array($ability, $data['abilities'], true))>

                                        <span class="min-w-0">
                                            <span class="font-mono" dir="ltr">{{ $ability }}</span>

                                            {{--
                                                «محصورة» تذكيرٌ لا منع.

                                                الصلاحية وحدها لا تكفي: المدرّس
                                                يرى كورساته هو لا كورسات زميله،
                                                وحصرُ النطاق يعمل معها دائماً.
                                            --}}
                                            @if(in_array($ability, $scoped, true))
                                                <span class="text-2xs text-info">· {{ __('محصورة بما يملكه') }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-3 mt-5 pt-4 border-t border-line">
                    <x-ui.button type="submit">{{ __('احفظ') }}</x-ui.button>
                    <p class="text-2xs text-muted">
                        <span x-text="count"></span> {{ __('صلاحية مفعّلة') }}
                    </p>
                </div>
            </form>
        </x-ui.card>
    @endforeach

</div>
</x-layouts.admin>
