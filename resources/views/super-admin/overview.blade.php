@php
    $statusTones = [
        'trialing' => 'info', 'active' => 'success', 'past_due' => 'warning',
        'suspended' => 'danger', 'provisioning' => 'neutral',
        'cancelled' => 'neutral', 'archived' => 'neutral',
    ];
    $statuses = App\Core\Admin\Resources\Central\TenantResource::STATUSES;
    $modes    = App\Core\Admin\Resources\Central\TenantResource::MODES;
@endphp

<x-layouts.super-admin :title="__('نظرة عامة')" current="overview">
<div class="max-w-[1400px]">

    <x-ui.page-header :title="__('نظرة عامة')"
                      :subtitle="__('حالة أعمالنا الآن — لا حالة منصة مشترك بعينه.')" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <x-ui.stat :label="__('الإيراد الشهري المتكرر')" :value="$mrr->format()" />
        <x-ui.stat :label="__('مشتركون نشطون')" :value="number_format($active)" />
        <x-ui.stat :label="__('في التجربة المجانية')" :value="number_format($trialing)"
                   :delta="$trialing > 0 ? __('فرص تحويل') : null" />
        <x-ui.stat :label="__('متعثّرون أو معلَّقون')" :value="number_format($pastDue + $suspended)"
                   :delta="$pastDue + $suspended > 0 ? __('يحتاجون متابعة') : null"
                   :trend="$pastDue + $suspended > 0 ? 'down' : null" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3 mb-6">
        <x-ui.card :title="__('التوزيع حسب الحالة')">
            @if($byStatus->isEmpty())
                <p class="text-sm text-subtle">{{ __('لا بيانات بعد.') }}</p>
            @else
                <div class="grid gap-3">
                    @foreach($byStatus as $status => $count)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <x-ui.badge :tone="$statusTones[$status] ?? 'neutral'">{{ __($statuses[$status] ?? $status) }}</x-ui.badge>
                            <span class="font-mono tabular">{{ number_format($count) }}</span>
                        </div>
                        <x-ui.progress :value="$total > 0 ? $count / $total * 100 : 0"
                                       :tone="$statusTones[$status] === 'danger' ? 'absent' : 'progress'"
                                       :label="__($statuses[$status] ?? $status)" />
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('التوزيع حسب النمط')">
            @if($byMode->isEmpty())
                <p class="text-sm text-subtle">{{ __('لا بيانات بعد.') }}</p>
            @else
                <x-ui.description-list :items="collect($byMode)
                    ->mapWithKeys(fn ($c, $m) => [__($modes[$m] ?? $m) => number_format($c)])->all()" />
            @endif
        </x-ui.card>

        <x-ui.card :title="__('التوزيع حسب الدولة')">
            @if($byCountry->isEmpty())
                <p class="text-sm text-subtle">{{ __('لا بيانات بعد.') }}</p>
            @else
                <x-ui.description-list :items="collect($byCountry)
                    ->mapWithKeys(fn ($c, $code) => [$code => number_format($c)])->all()" />
            @endif
        </x-ui.card>
    </div>

    <x-ui.card :title="__('أحدث المشتركين')" :padding="false">
        <x-slot:actions>
            <x-ui.button size="sm" variant="secondary" :href="url('/admin/tenants')">{{ __('عرض الكل') }}</x-ui.button>
        </x-slot:actions>

        @if($recent->isEmpty())
            <div class="p-5">
                <x-ui.empty :title="__('لا يوجد مشتركون بعد')">
                    {{ __('سيظهر هنا كل من ينشئ منصّته على المنصة.') }}
                </x-ui.empty>
            </div>
        @else
            <x-ui.table>
                <thead>
                    <tr>
                        @foreach ([__('المشترك'), __('النطاق'), __('الحالة'), __('النمط'), __('الباقة'), __('منذ')] as $th)
                            <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($recent as $tenant)
                        <tr class="hover:bg-surface-sunken transition-colors">
                            <td class="px-4 py-3 border-b border-line">
                                <div class="font-medium">{{ $tenant->name }}</div>
                                <div class="text-xs text-subtle">{{ $tenant->owner_email }}</div>
                            </td>
                            <td class="px-4 py-3 border-b border-line font-mono text-xs">{{ $tenant->domains->firstWhere('is_primary', true)?->domain }}</td>
                            <td class="px-4 py-3 border-b border-line">
                                <x-ui.badge :tone="$statusTones[$tenant->status] ?? 'neutral'">{{ __($statuses[$tenant->status] ?? $tenant->status) }}</x-ui.badge>
                            </td>
                            <td class="px-4 py-3 border-b border-line text-sm">{{ __($modes[$tenant->platform_mode] ?? $tenant->platform_mode) }}</td>
                            <td class="px-4 py-3 border-b border-line text-sm">{{ $tenant->plan_key }}</td>
                            <td class="px-4 py-3 border-b border-line font-mono text-xs whitespace-nowrap">{{ $tenant->created_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
</x-layouts.super-admin>
