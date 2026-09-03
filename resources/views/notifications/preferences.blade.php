<x-layouts.app :title="__('تفضيلات الإشعارات')">
<x-site.header />

<main id="main" class="max-w-[900px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('تفضيلات الإشعارات')"
                      :subtitle="__('اختر ما يصلك وعلى أي قناة. الأحداث الأمنية لا تُطفأ.')"
                      :back="url('/notifications')" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    {{-- إشعارات المتصفّح تحتاج إذناً من المتصفّح نفسه، لا خانةً في جدول --}}
    @if((bool) setting('notifications.web_push_enabled', false) && filled(setting('notifications.vapid_public')))
        <div class="surface-card p-4 mb-5 flex flex-wrap items-center gap-3"
             x-data="pushToggle('{{ setting('notifications.vapid_public') }}', '{{ csrf_token() }}')" x-cloak>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm">{{ __('إشعارات هذا الجهاز') }}</p>
                <p class="text-2xs text-subtle mt-0.5" x-show="! denied">{{ __('تصلك حتى وأنت خارج الموقع.') }}</p>
                <p class="text-2xs text-danger mt-0.5" x-show="denied">{{ __('منعت المتصفّح من الإشعارات — اسمح بها من إعدادات الموقع في متصفّحك.') }}</p>
                <p class="text-2xs text-subtle mt-0.5" x-show="! supported">{{ __('متصفّحك لا يدعم إشعارات الويب.') }}</p>
            </div>

            <button type="button" @click="toggle()" x-show="supported && ! denied"
                    :disabled="busy"
                    :class="on ? 'bg-surface border-line-strong' : 'bg-primary text-primary-on border-transparent'"
                    class="min-h-11 px-5 rounded-md border font-semibold text-sm transition-colors disabled:opacity-50 w-full sm:w-auto sm:shrink-0">
                <span x-show="! on">{{ __('فعّل على هذا الجهاز') }}</span>
                <span x-show="on">{{ __('أوقف على هذا الجهاز') }}</span>
            </button>
        </div>
    @endif

    <div class="surface-card p-4 mb-5 flex flex-wrap items-center gap-3" x-data="installPrompt()" x-show="available" x-cloak>
        <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm">{{ __('ثبّت التطبيق') }}</p>
            <p class="text-2xs text-subtle mt-0.5">{{ __('أيقونة على شاشتك، وفتح أسرع، ومتابعة دروسك بلا متصفّح.') }}</p>
        </div>
        <button type="button" @click="install()"
                class="min-h-11 px-5 rounded-md bg-primary text-primary-on font-semibold text-sm w-full sm:w-auto sm:shrink-0">
            {{ __('ثبّت') }}
        </button>
    </div>

    @if($channels === [])
        <x-ui.card>
            <x-ui.empty :title="__('لا قنوات مفعّلة')">{{ __('لم يفعّل الموقع أي قناة إشعارات بعد.') }}</x-ui.empty>
        </x-ui.card>
    @else
        <form method="POST" action="{{ url('/account/notifications') }}" class="flex flex-col gap-5">
            @csrf
            @method('PUT')

            @foreach($groups as $group => $events)
                @php
                    $visible = collect($events)->reject(fn ($e) => $e->isMandatory());
                @endphp
                @continue($visible->isEmpty())

                <section class="surface-card overflow-hidden">
                    <h2 class="px-4 py-3 bg-surface-sunken border-b border-default font-bold text-sm">
                        {{ __($groupLabels[$group] ?? $group) }}
                    </h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[520px]">
                            <thead>
                                <tr class="text-2xs text-subtle border-b border-default">
                                    <th class="text-start font-semibold px-4 py-2.5">{{ __('الحدث') }}</th>
                                    @foreach($channels as $key => $channel)
                                        <th class="font-semibold px-3 py-2.5 w-24">{{ $channel->label() }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--color-line)]">
                                @foreach($visible as $event)
                                    @php $rows = $overrides->get($event->key)?->keyBy('channel'); @endphp
                                    <tr>
                                        <td class="px-4 py-2.5 font-medium">{{ __($event->label) }}</td>

                                        @foreach($channels as $key => $channel)
                                            <td class="px-3 py-2.5 text-center">
                                                @if($event->allows($key))
                                                    @php
                                                        $row = $rows?->get($key);
                                                        $on = $row === null ? $event->isOnByDefault($key) : (bool) $row->is_enabled;
                                                    @endphp
                                                    <label class="inline-flex items-center justify-center size-11 cursor-pointer">
                                                        <input type="checkbox" name="enabled[{{ $event->key }}][{{ $key }}]" value="1"
                                                               @checked($on) class="size-5 accent-[var(--color-primary)]">
                                                        <span class="sr-only">{{ __($event->label) }} — {{ $channel->label() }}</span>
                                                    </label>
                                                @else
                                                    <span class="text-subtle" aria-label="{{ __('غير متاح') }}">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach

            <x-ui.button type="submit" class="self-start">{{ __('حفظ التفضيلات') }}</x-ui.button>
        </form>
    @endif
</main>

<x-site.footer />
</x-layouts.app>
