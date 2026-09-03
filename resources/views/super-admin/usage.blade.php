@php
    use App\Core\Admin\Resources\Central\TenantResource;
    $statuses = TenantResource::STATUSES;
@endphp

<x-layouts.super-admin :title="__('الاستهلاك والحدود')" current="usage">
<div class="max-w-[1400px]">

    <x-ui.page-header :title="__('الاستهلاك والحدود')"
                      :subtitle="__('من اقترب من سقفه — وهم أقرب من يُرقّي باقته.')" />

    @if($features->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا مزايا ذات حدود')">
                {{ __('عرّف ميزة من نوع «حد ثابت» أو «حصة متجدّدة» ليظهر استهلاكها هنا.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
            <x-ui.field :label="__('الميزة')" for="feature" class="mb-0 w-full sm:w-72">
                <x-ui.select name="feature" id="feature" onchange="this.form.submit()">
                    @foreach($features as $option)
                        <option value="{{ $option->key }}" @selected($option->key === $featureKey)>
                            {{ $option->name[app()->getLocale()] ?? $option->name['ar'] ?? $option->key }}
                        </option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <noscript><x-ui.button size="sm" variant="secondary" type="submit">{{ __('عرض') }}</x-ui.button></noscript>
        </form>

        <div class="grid gap-4 sm:grid-cols-3 mb-6">
            <x-ui.stat :label="__('مشتركون في هذه الميزة')" :value="number_format($rows->count())" />
            <x-ui.stat :label="__('تجاوزوا ٨٠٪ من حدّهم')" :value="number_format($atRisk)"
                       :delta="$atRisk > 0 ? __('فرص ترقية') : null" :trend="$atRisk > 0 ? 'up' : null" />
            <x-ui.stat :label="__('الوحدة')" :value="$feature?->unit ?? '—'" />
        </div>

        <x-ui.card :title="$feature?->name[app()->getLocale()] ?? $feature?->name['ar'] ?? $featureKey" :padding="false">
            @if($rows->isEmpty())
                <div class="p-5">
                    <x-ui.empty :title="__('لا مشتركين نشطين')">{{ __('سيظهر الاستهلاك هنا فور اشتراك أول عميل.') }}</x-ui.empty>
                </div>
            @else
                <div class="overflow-x-auto">
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('المشترك'), __('الباقة'), __('الحالة'), __('المستهلَك'), __('الحد'), __('النسبة')] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr class="hover:bg-surface-sunken transition-colors">
                                    <td class="px-4 py-3 border-b border-line">
                                        <a href="{{ url('/admin/tenants/'.$row['tenant']->id) }}" class="tap-link text-sm font-medium text-primary hover:underline">{{ $row['tenant']->name }}</a>
                                        <div class="text-2xs text-subtle">{{ $row['tenant']->owner_email }}</div>
                                    </td>
                                    <td class="px-4 py-3 border-b border-line text-sm whitespace-nowrap">{{ $row['tenant']->plan_key }}</td>
                                    <td class="px-4 py-3 border-b border-line">
                                        <x-ui.badge :tone="match($row['tenant']->status) { 'active' => 'success', 'trialing' => 'info', 'past_due' => 'warning', default => 'neutral' }">
                                            {{ __($statuses[$row['tenant']->status] ?? $row['tenant']->status) }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular">{{ number_format($row['used']) }}</td>
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular text-muted">
                                        {{ $row['limit'] === null ? __('بلا حد') : number_format($row['limit']) }}
                                    </td>
                                    <td class="px-4 py-3 border-b border-line min-w-[160px]">
                                        @if($row['percent'] === null)
                                            <span class="text-subtle text-sm">—</span>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <x-ui.progress :value="$row['percent']"
                                                               :tone="$row['percent'] >= 95 ? 'absent' : ($row['percent'] >= 80 ? 'late' : 'progress')"
                                                               :label="$row['tenant']->name" class="flex-1" />
                                                <span class="font-mono text-2xs tabular text-muted shrink-0">{{ $row['percent'] }}%</span>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
            @endif
        </x-ui.card>
    @endif
</div>
</x-layouts.super-admin>
