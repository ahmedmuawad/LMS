<x-layouts.super-admin :title="__('سجلّ التدخّلات')" current="audit">
<div class="max-w-[1200px]">

    <x-ui.page-header :title="__('سجلّ التدخّلات')"
                      :subtitle="__('كل فعل من فريقنا في حساب عميل: من فعله، ومتى، ومن أي عنوان.')" />

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
        <x-ui.field :label="__('نوع الفعل')" for="action" class="mb-0 w-full sm:w-72">
            <x-ui.select name="action" id="action" onchange="this.form.submit()">
                <option value="">{{ __('كل الأفعال') }}</option>
                @foreach($actions as $value => $label)
                    <option value="{{ $value }}" @selected(request('action') === $value)>{{ __($label) }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <noscript><x-ui.button size="sm" variant="secondary" type="submit">{{ __('تصفية') }}</x-ui.button></noscript>
    </form>

    <x-ui.card :padding="false">
        @if($entries->isEmpty())
            <div class="p-5">
                <x-ui.empty :title="__('لا تدخّلات مسجّلة')">
                    {{ __('لم يتدخّل أحد من الفريق في أي حساب بعد — وهذا هو الوضع الطبيعي.') }}
                </x-ui.empty>
            </div>
        @else
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            @foreach ([__('الفعل'), __('المشترك'), __('من فعله'), __('التفاصيل'), __('العنوان'), __('متى')] as $th)
                                <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $entry)
                            <tr class="hover:bg-surface-sunken transition-colors align-top">
                                <td class="px-4 py-3 border-b border-line text-sm font-medium whitespace-nowrap">{{ $entry->actionLabel() }}</td>
                                <td class="px-4 py-3 border-b border-line text-sm">
                                    @if($entry->tenant)
                                        <a href="{{ url('/admin/tenants/'.$entry->tenant_id) }}" class="tap-link text-primary hover:underline">{{ $entry->tenant->name }}</a>
                                    @else
                                        <span class="text-subtle">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 border-b border-line text-sm whitespace-nowrap">{{ $entry->actor_name }}</td>
                                <td class="px-4 py-3 border-b border-line text-xs text-muted max-w-xs break-words">
                                    {{ collect($entry->meta ?? [])->filter()->map(fn ($v, $k) => $k.': '.$v)->implode(' · ') ?: '—' }}
                                </td>
                                <td class="px-4 py-3 border-b border-line font-mono text-2xs text-subtle whitespace-nowrap">{{ $entry->ip }}</td>
                                <td class="px-4 py-3 border-b border-line text-xs text-subtle whitespace-nowrap">{{ $entry->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
            <x-slot:footer>
                <x-ui.pagination :current="$entries->currentPage()" :last="$entries->lastPage()"
                                 :url="request()->fullUrlWithQuery(['page' => '']).''" />
            </x-slot:footer>
        @endif
    </x-ui.card>
</div>
</x-layouts.super-admin>
