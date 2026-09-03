<x-layouts.admin :title="__($event->label)">

<x-ui.page-header :title="__($event->label)" :subtitle="$event->key"
                  :back="route('admin.notifications.matrix')">
    <x-slot:actions>
        <form method="POST" action="{{ route('admin.notifications.test', ['event' => $event->key]) }}">
            @csrf
            <x-ui.button type="submit" variant="secondary">{{ __('أرسل تجربة لي') }}</x-ui.button>
        </form>
    </x-slot:actions>
</x-ui.page-header>

@if(session('status'))
    <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
@endif

<div class="grid gap-5 lg:grid-cols-[1fr_280px] items-start">

    <form method="POST" action="{{ route('admin.notifications.update', ['event' => $event->key]) }}"
          class="min-w-0 flex flex-col gap-4">
        @csrf
        @method('PUT')

        @foreach($channels as $key => $channel)
            @php $template = $templates->get($key); @endphp

            <section class="surface-card overflow-hidden">
                <header class="flex flex-wrap items-center gap-3 px-4 py-3 bg-surface-sunken border-b border-default">
                    <h2 class="font-bold text-sm flex-1 min-w-0">{{ $channel->label() }}</h2>

                    @unless($channel->isReady())
                        <x-ui.badge tone="warning">{{ __('غير مُعدّة') }}</x-ui.badge>
                    @endunless

                    <label class="inline-flex items-center gap-2 cursor-pointer min-h-11">
                        <input type="checkbox" name="templates[{{ $key }}][is_enabled]" value="1"
                               @checked($template === null ? $event->isOnByDefault($key) : $template->is_enabled)
                               class="size-5 accent-[var(--color-primary)]">
                        <span class="text-sm">{{ __('مفعّلة') }}</span>
                    </label>
                </header>

                <div class="p-4 flex flex-col gap-4">
                    @if($key === 'whatsapp')
                        <x-ui.field :label="__('اسم القالب المعتمد من ميتا')" for="tpl-{{ $key }}" class="mb-0"
                                    :hint="__('اتركه فارغاً لإرسال نصّ حرّ — ولا يمرّ إلا داخل نافذة ٢٤ ساعة من ردّ العميل.')">
                            <x-ui.input name="templates[{{ $key }}][provider_template]" id="tpl-{{ $key }}"
                                        class="font-mono" value="{{ $template?->provider_template }}" />
                        </x-ui.field>
                    @endif

                    @foreach($locales as $locale)
                        <div class="flex flex-col gap-2 pt-3 first:pt-0 border-t first:border-t-0 border-default">
                            <span class="font-mono text-2xs text-subtle">{{ $locale }}</span>

                            @if(in_array($key, ['mail', 'database'], true))
                                <x-ui.field :label="__('العنوان')" for="{{ $key }}-subject-{{ $locale }}" class="mb-0">
                                    <x-ui.input name="templates[{{ $key }}][subject][{{ $locale }}]"
                                                id="{{ $key }}-subject-{{ $locale }}"
                                                value="{{ $template?->getTranslation('subject', $locale) }}" />
                                </x-ui.field>
                            @endif

                            <x-ui.field :label="__('النص')" for="{{ $key }}-body-{{ $locale }}" class="mb-0">
                                <x-ui.textarea name="templates[{{ $key }}][body][{{ $locale }}]"
                                               id="{{ $key }}-body-{{ $locale }}"
                                               rows="{{ in_array($key, ['sms', 'whatsapp'], true) ? 3 : 6 }}"
                                >{{ $template?->getTranslation('body', $locale) }}</x-ui.textarea>
                            </x-ui.field>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <x-ui.button type="submit" class="self-start">{{ __('حفظ القوالب') }}</x-ui.button>
    </form>

    <aside class="surface-card p-4 lg:sticky lg:top-6">
        <h2 class="font-bold text-sm mb-2">{{ __('المتغيّرات المتاحة') }}</h2>
        <p class="text-2xs text-subtle mb-3 leading-relaxed">
            {{ __('اكتبها هكذا داخل النص. ما ليس في هذه القائمة يُمحى ولا يُعرض على المستلم.') }}
        </p>

        <ul class="flex flex-col gap-1.5">
            @foreach($event->variables as $variable)
                {{-- الأقواس تُركَّب في PHP: Blade لا يقرأ قوسين داخل قوسين --}}
                @php $placeholder = '{'.'{ '.$variable.' }'.'}'; @endphp
                <li>
                    <code class="text-2xs font-mono bg-surface-sunken text-muted px-2 py-1 rounded-md block break-all">{{ $placeholder }}</code>
                </li>
            @endforeach
        </ul>

        <div class="mt-4 pt-4 border-t border-default text-2xs text-subtle leading-relaxed">
            <p class="mb-1"><span class="font-semibold text-muted">{{ __('المستلم:') }}</span>
                {{ __(['student' => 'الطالب', 'instructor' => 'المدرّس', 'guardian' => 'ولي الأمر', 'staff' => 'الفريق', 'customer' => 'العميل'][$event->audience] ?? $event->audience) }}</p>
            @if($event->isMandatory())
                <p class="text-warning">{{ __('حدث أمني — لا يستطيع المستخدم إطفاؤه.') }}</p>
            @endif
        </div>
    </aside>
</div>

</x-layouts.admin>
