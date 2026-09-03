<x-layouts.admin :title="__('سجلّ الإشعارات')">

<x-ui.page-header :title="__('سجلّ الإرسال')"
                  :subtitle="__('ما أُرسل فعلاً ومتى وبأي نتيجة — به وحده يُحسم «ما وصلني إشعار».')"
                  :back="route('admin.notifications.matrix')" />

<form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <x-ui.field :label="__('الحدث')" for="event" class="mb-0 w-full sm:w-64">
        <x-ui.select name="event" id="event">
            <option value="">{{ __('كل الأحداث') }}</option>
            @foreach($events as $key => $event)
                <option value="{{ $key }}" @selected(request('event') === $key)>{{ __($event->label) }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.field>

    <x-ui.field :label="__('القناة')" for="channel" class="mb-0 w-full sm:w-44">
        <x-ui.select name="channel" id="channel">
            <option value="">{{ __('كل القنوات') }}</option>
            @foreach($channels as $key => $channel)
                <option value="{{ $key }}" @selected(request('channel') === $key)>{{ $channel->label() }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.field>

    <x-ui.field :label="__('الحالة')" for="status" class="mb-0 w-full sm:w-44">
        <x-ui.select name="status" id="status">
            <option value="">{{ __('الكل') }}</option>
            @foreach(['queued' => 'في الطابور', 'sent' => 'أُرسل', 'failed' => 'فشل', 'skipped' => 'تُخطّي'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ __($label) }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.field>

    <x-ui.button size="sm" type="submit" class="h-11">{{ __('تصفية') }}</x-ui.button>
</form>

@if($logs->isEmpty())
    <x-ui.card>
        <x-ui.empty :title="__('لا سجلّات')">{{ __('لم يُرسل شيء بعد بهذه الفلاتر.') }}</x-ui.empty>
    </x-ui.card>
@else
    <div class="surface-card overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
            <thead class="bg-surface-sunken text-2xs text-subtle">
                <tr>
                    <th class="text-start font-semibold px-4 py-3">{{ __('الحدث') }}</th>
                    <th class="text-start font-semibold px-4 py-3">{{ __('القناة') }}</th>
                    <th class="text-start font-semibold px-4 py-3">{{ __('المستلم') }}</th>
                    <th class="text-start font-semibold px-4 py-3">{{ __('الحالة') }}</th>
                    <th class="text-start font-semibold px-4 py-3">{{ __('الوقت') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @foreach($logs as $log)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-mono text-2xs">{{ $log->event }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $channels[$log->channel]?->label() ?? $log->channel }}</td>
                        <td class="px-4 py-3 min-w-0">
                            <span class="block truncate max-w-[220px]">{{ $log->user?->name ?? '—' }}</span>
                            @if($log->destination)
                                <span class="block text-2xs text-subtle font-mono truncate max-w-[220px]">{{ $log->destination }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-ui.badge :tone="match($log->status) {
                                'sent' => 'success', 'failed' => 'danger',
                                'skipped' => 'neutral', default => 'info',
                            }">
                                {{ __(['queued' => 'في الطابور', 'sent' => 'أُرسل', 'failed' => 'فشل', 'skipped' => 'تُخطّي'][$log->status] ?? $log->status) }}
                            </x-ui.badge>
                            @if($log->reason)
                                <span class="block text-2xs text-subtle mt-0.5 max-w-[240px]">{{ $log->reason }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-2xs text-subtle whitespace-nowrap">
                            {{ ($log->sent_at ?? $log->created_at)?->translatedFormat('j M · H:i') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="mt-6">
            <x-ui.pagination :current="$logs->currentPage()" :last="$logs->lastPage()"
                             :url="request()->fullUrlWithQuery(['page' => '']).''" />
        </div>
    @endif
@endif

</x-layouts.admin>
