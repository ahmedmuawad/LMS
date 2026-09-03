<x-layouts.admin :title="__('مصفوفة الإشعارات')">

<form method="POST" action="{{ route('admin.notifications.matrix.save') }}">
    @csrf
    @method('PUT')

    <x-ui.page-header :title="__('الإشعارات')"
                      :subtitle="__('أي حدث يُرسل على أي قناة. اضغط اسم الحدث لتحرير نصّه.')">
        <x-slot:actions>
            <x-ui.button as="a" :href="route('admin.notifications.logs')" variant="secondary">{{ __('سجلّ الإرسال') }}</x-ui.button>
            <x-ui.button type="submit">{{ __('حفظ') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @php $notReady = collect($channels)->reject(fn ($c) => $c->isReady()); @endphp

    @if($notReady->isNotEmpty())
        <x-ui.alert tone="info" :title="__('قنوات غير مُعدّة')" class="mb-4">
            {{ __('لن يُرسل شيء على: :channels — اضبط مفاتيحها في إعدادات الإشعارات.', [
                'channels' => $notReady->map(fn ($c) => $c->label())->implode('، '),
            ]) }}
        </x-ui.alert>
    @endif

    <div class="flex flex-col gap-6">
        @foreach($groups as $group => $events)
            <section class="surface-card overflow-hidden">
                <h2 class="px-4 py-3 bg-surface-sunken border-b border-default font-bold text-sm">
                    {{ __($groupLabels[$group] ?? $group) }}
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[640px]">
                        <thead>
                            <tr class="text-2xs text-subtle border-b border-default">
                                <th class="text-start font-semibold px-4 py-2.5">{{ __('الحدث') }}</th>
                                @foreach($channels as $key => $channel)
                                    <th class="font-semibold px-3 py-2.5 w-24">
                                        <span @class(['opacity-45' => ! $channel->isReady()])>{{ $channel->label() }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-line)]">
                            @foreach($events as $event)
                                @php $rows = $templates->get($event->key)?->keyBy('channel'); @endphp
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('admin.notifications.edit', ['event' => $event->key]) }}"
                                           class="tap-link font-semibold hover:text-primary transition-colors">{{ __($event->label) }}</a>
                                        <p class="text-2xs text-subtle font-mono mt-0.5">{{ $event->key }}</p>
                                    </td>

                                    @foreach($channels as $key => $channel)
                                        <td class="px-3 py-2.5 text-center">
                                            @if($event->allows($key))
                                                @php
                                                    $row = $rows?->get($key);
                                                    $on = $row === null ? $event->isOnByDefault($key) : (bool) $row->is_enabled;
                                                @endphp
                                                <label class="inline-flex items-center justify-center size-11 cursor-pointer">
                                                    <input type="checkbox" name="enabled[{{ $event->key }}][{{ $key }}]" value="1"
                                                           @checked($on) @disabled(! $channel->isReady())
                                                           class="size-5 accent-[var(--color-primary)] disabled:opacity-40">
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
    </div>
</form>

</x-layouts.admin>
