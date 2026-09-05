@php
    // من الإعداد نفسه: نمط يُضاف هناك لا يُنسى هنا
    $modes = collect(config('platform-modes.modes'))
        ->map(fn (array $m): string => $m['name'][app()->getLocale()] ?? $m['name']['ar'])
        ->all();
    $deliveries = ['recorded' => 'مسجّل', 'live' => 'مباشر', 'blended' => 'مدمج'];
    $roles = [
        'owner' => 'المالك', 'admin' => 'مدير', 'instructor' => 'مدرّس',
        'staff' => 'موظف', 'student' => 'طالب', 'guardian' => 'ولي أمر',
    ];
@endphp

<x-layouts.admin :title="__('اللوحة')" current="dashboard">
<div class="max-w-[1400px]">

    <x-ui.page-header :title="__('أهلاً بك في :name', ['name' => site_name()])"
                      :subtitle="__('نظرة سريعة على منصّتك: من فيها، وما استهلكته من باقتك.')" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <x-ui.stat :label="__('الطلاب')" :value="number_format($students)" />
        <x-ui.stat :label="__('فريق المنصة')" :value="number_format($staff)" />
        <x-ui.stat :label="__('الموديولات المفعّلة')" :value="number_format($modules->count())" />
        <x-ui.stat :label="__('الباقة')"
                   :value="$plan?->name[app()->getLocale()] ?? $plan?->name['ar'] ?? ($tenant->plan_key ?? '—')"
                   :delta="$tenant->onTrial() ? __('تجربة مجانية') : null" />
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">

        <x-ui.card :title="__('استهلاكك من الباقة')">
            <x-slot:actions>
                <x-ui.button size="sm" variant="ghost" :href="url('/admin/billing')">{{ __('ترقية') }}</x-ui.button>
            </x-slot:actions>

            @if($usage === [])
                <x-ui.empty :title="__('لا حدود على باقتك')">
                    {{ __('كل ما تستخدمه بلا سقف — سيظهر هنا أي حدّ يُضاف لاحقاً.') }}
                </x-ui.empty>
            @else
                <div class="grid gap-4">
                    @foreach($usage as $row)
                        <div>
                            <div class="flex items-baseline justify-between gap-3 mb-1.5 text-sm">
                                <span class="min-w-0 truncate">{{ $row['label'] }}</span>
                                <span class="font-mono text-xs tabular shrink-0 text-muted">
                                    {{ number_format($row['used']) }}
                                    @if($row['limit'] === null)
                                        <span class="text-subtle">/ {{ __('بلا حد') }}</span>
                                    @else
                                        <span class="text-subtle">/ {{ number_format($row['limit']) }}</span>
                                    @endif
                                </span>
                            </div>
                            <x-ui.progress :value="$row['percent'] ?? 0"
                                           :tone="($row['percent'] ?? 0) >= 95 ? 'absent' : (($row['percent'] ?? 0) >= 80 ? 'late' : 'progress')"
                                           :label="$row['label']" />
                            @if(($row['percent'] ?? 0) >= 80)
                                <p class="text-2xs text-muted mt-1.5">
                                    {{ ($row['percent'] ?? 0) >= 95
                                        ? __('أوشكت على بلوغ الحد — ما هو قائم يبقى يعمل، لكن الإضافة الجديدة ستتوقّف.')
                                        : __('اقتربت من الحد. راجع باقتك قبل أن تتوقّف الإضافة الجديدة.') }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <div class="grid gap-4 content-start">
            <x-ui.card :title="__('هوية منصّتك')">
                <x-ui.description-list :items="[
                    __('النمط')        => __($modes[$tenant->platform_mode] ?? $tenant->platform_mode),
                    __('طريقة التقديم') => __($deliveries[$tenant->delivery_mode] ?? $tenant->delivery_mode),
                    {{-- «السنتر» لفظٌ يخصّ من يملك مبنى؛ والمدرّس المستقل
                         يقرأ «إدارة السنتر: مفعّلة» فيظنّ أنه اشترى ما لم يشترِه.
                         السطر يصف الأداة لا المكان. --}}
                    __('المجموعات والحضور') => $tenant->managesCenter() ? __('مفعّلة') : __('غير مفعّلة'),
                    __('الدولة والعملة') => $tenant->country.' · '.$tenant->currency,
                    __('لغة الواجهة')   => $tenant->locale === 'ar' ? __('العربية') : __('الإنجليزية'),
                ]" />
            </x-ui.card>

            <x-ui.card :title="__('من في منصّتك')">
                @if($byRole->isEmpty())
                    <p class="text-sm text-subtle">{{ __('لا مستخدمين بعد.') }}</p>
                @else
                    <x-ui.description-list :items="collect($byRole)
                        ->mapWithKeys(fn ($count, $role) => [__($roles[$role] ?? $role) => number_format($count)])->all()" />
                @endif
                <x-slot:actions>
                    <x-ui.button size="sm" variant="secondary" :href="url('/admin/users')">{{ __('إدارة المستخدمين') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.card>
        </div>
    </div>
</div>
</x-layouts.admin>
