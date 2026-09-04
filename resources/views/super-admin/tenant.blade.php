@php
    use App\Core\Admin\Resources\Central\TenantResource;
    use App\Http\Controllers\Admin\TenantController;

    $statusTones = [
        'trialing' => 'info', 'active' => 'success', 'past_due' => 'warning',
        'suspended' => 'danger', 'provisioning' => 'neutral',
        'cancelled' => 'neutral', 'archived' => 'neutral',
    ];
    $statuses   = TenantResource::STATUSES;
    $modes      = TenantResource::MODES;
    $deliveries = ['recorded' => 'مسجّل', 'live' => 'مباشر', 'blended' => 'مدمج'];
    $roles      = ['owner' => 'المالك', 'admin' => 'مدير', 'instructor' => 'مدرّس', 'staff' => 'موظف'];
    $primary    = $tenant->domains->firstWhere('is_primary', true) ?? $tenant->domains->first();
@endphp

<x-layouts.super-admin :title="$tenant->name" current="tenants">
<div class="max-w-[1400px]">

    <x-ui.page-header :title="$tenant->name"
                      :subtitle="$tenant->owner_email"
                      :back="url('/admin/tenants')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('المشتركون'), 'url' => url('/admin/tenants')],
                ['label' => $tenant->name],
            ]" />
        </x-slot:breadcrumb>

        <x-slot:actions>
            @if($primary)
                <x-ui.button size="sm" variant="ghost" :href="'//'.$primary->domain" target="_blank" rel="noopener">
                    {{ __('زيارة الموقع') }}
                </x-ui.button>
            @endif

            <form method="POST" action="{{ url('/admin/tenants/'.$tenant->id.'/impersonate') }}"
                  x-data @submit="if (! confirm(@js(__('سيُسجَّل دخولك إلى حساب هذا المشترك باسمك في سجلّ التدخّلات. متابعة؟')))) $event.preventDefault()">
                @csrf
                <x-ui.button size="sm" variant="secondary" type="submit"
                             :disabled="! $tenant->canAccessDashboard()">
                    {{ __('الدخول كمشترك') }}
                </x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <x-ui.badge :tone="$statusTones[$tenant->status] ?? 'neutral'">{{ __($statuses[$tenant->status] ?? $tenant->status) }}</x-ui.badge>
        <x-ui.badge tone="primary">{{ __($modes[$tenant->platform_mode] ?? $tenant->platform_mode) }}</x-ui.badge>
        <x-ui.badge>{{ __($deliveries[$tenant->delivery_mode] ?? $tenant->delivery_mode) }}</x-ui.badge>
        @if($tenant->managesCenter())<x-ui.badge tone="accent">{{ __('إدارة سنتر') }}</x-ui.badge>@endif
        @if($tenant->onTrial())
            <x-ui.badge tone="info">{{ trans_choice('{1} بقي يوم في التجربة|{2} بقي يومان في التجربة|[3,10] بقيت :count أيام في التجربة|[11,*] بقي :count يوماً في التجربة', $tenant->trialDaysLeft(), ['count' => $tenant->trialDaysLeft()]) }}</x-ui.badge>
        @endif
        @if($primary)<span class="font-mono text-xs text-subtle">{{ $primary->domain }}</span>@endif
    </div>

    @if($health['error'])
        <x-ui.alert tone="danger" :title="__('تعثّر تجهيز هذا المشترك')" class="mb-6">{{ $health['error'] }}</x-ui.alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px] items-start">

        {{-- ===== العمود الرئيسي ===== --}}
        <div class="grid gap-4 min-w-0">

            <x-ui.card :title="__('المزايا والحدود الفعّالة')"
                       :subtitle="__('ما يراه المشترك فعلاً — وما جاء منه من الباقة وما هو استثناء خاص به.')"
                       :padding="false">
                <div class="overflow-x-auto">
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('الميزة'), __('من الباقة'), __('استثناء'), __('الفعلي'), __('الاستهلاك'), ''] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($features as $row)
                                <tr class="hover:bg-surface-sunken transition-colors align-top">
                                    <td class="px-4 py-3 border-b border-line">
                                        <div class="text-sm font-medium">{{ $row['label'] }}</div>
                                        <div class="font-mono text-2xs text-subtle">{{ $row['key'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 border-b border-line text-sm text-muted whitespace-nowrap">
                                        {{ TenantController::valueLabel($row['fromPlan']) }}
                                    </td>
                                    <td class="px-4 py-3 border-b border-line whitespace-nowrap">
                                        @if($row['override'] !== null)
                                            <x-ui.badge tone="accent">{{ TenantController::valueLabel($row['override']) }}</x-ui.badge>
                                        @else
                                            <span class="text-subtle text-sm">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 border-b border-line text-sm font-semibold whitespace-nowrap">
                                        {{ TenantController::valueLabel($row['effective']) }}
                                    </td>
                                    <td class="px-4 py-3 border-b border-line min-w-[140px]">
                                        @if($row['type'] === 'boolean')
                                            <span class="text-subtle text-sm">—</span>
                                        @else
                                            <div class="font-mono text-xs tabular mb-1">{{ number_format($row['used']) }}</div>
                                            @if($row['percent'] !== null)
                                                <x-ui.progress :value="$row['percent']"
                                                               :tone="$row['percent'] >= 95 ? 'absent' : ($row['percent'] >= 80 ? 'late' : 'progress')"
                                                               :label="$row['label']" />
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 border-b border-line">
                                        <form method="POST" action="{{ url('/admin/tenants/'.$tenant->id.'/feature') }}"
                                              class="flex items-center gap-1.5">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="feature_key" value="{{ $row['key'] }}">
                                            <x-ui.input name="value" value="{{ $row['override'] }}"
                                                        class="w-24 !py-1.5 font-mono text-xs"
                                                        :placeholder="__('استثناء')"
                                                        :aria-label="__('استثناء لـ :feature', ['feature' => $row['label']])" />
                                            <x-ui.button size="sm" variant="ghost" type="submit">{{ __('حفظ') }}</x-ui.button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
                <x-slot:footer>
                    <p class="text-2xs text-subtle">
                        {{ __('القيم: رقم للحدود، unlimited لبلا حد، 1 أو 0 للمزايا المنطقية. اترك الخانة فارغة للعودة إلى ما تقوله الباقة.') }}
                    </p>
                </x-slot:footer>
            </x-ui.card>

            <x-ui.card :title="__('من في منصّته')" :padding="false">
                @if($people === null)
                    <div class="p-5">
                        <x-ui.alert tone="warning" :title="__('تعذّر الوصول إلى قاعدة هذا المشترك')">
                            {{ __('قد تكون قيد التجهيز أو غير متاحة الآن. باقي بيانات الملف معروضة كما هي.') }}
                        </x-ui.alert>
                    </div>
                @elseif($people['staff']->isEmpty())
                    <div class="p-5"><x-ui.empty :title="__('لا مستخدمين بعد')">{{ __('لم يُنشئ هذا المشترك أي حساب داخل منصّته.') }}</x-ui.empty></div>
                @else
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('الاسم'), __('البريد'), __('الدور'), __('آخر ظهور')] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($people['staff'] as $person)
                                <tr class="hover:bg-surface-sunken transition-colors">
                                    <td class="px-4 py-3 border-b border-line text-sm font-medium">{{ $person['name'] }}</td>
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs">{{ $person['email'] }}</td>
                                    <td class="px-4 py-3 border-b border-line">
                                        <x-ui.badge :tone="$person['role'] === 'owner' ? 'primary' : 'neutral'">{{ __($roles[$person['role']] ?? $person['role']) }}</x-ui.badge>
                                    </td>
                                    <td class="px-4 py-3 border-b border-line text-xs text-subtle whitespace-nowrap">
                                        {{ $person['last_seen'] ?? __('لم يدخل بعد') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                    <x-slot:footer>
                        <p class="text-2xs text-subtle">
                            {{ __(':students طالباً · :total مستخدماً في المجمل', ['students' => number_format($people['students']), 'total' => number_format($people['total'])]) }}
                        </p>
                    </x-slot:footer>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('سجلّ تدخّلاتنا في هذا الحساب')">
                @if($log->isEmpty())
                    <x-ui.empty :title="__('لم نتدخّل في هذا الحساب')">
                        {{ __('كل تعليق أو تغيير باقة أو دخول كمشترك سيُقيَّد هنا باسم فاعله.') }}
                    </x-ui.empty>
                @else
                    <x-ui.timeline :items="$log->map(fn ($entry) => [
                        'title' => $entry->actionLabel(),
                        'meta'  => $entry->actor_name.' · '.$entry->created_at?->diffForHumans(),
                        'body'  => collect($entry->meta ?? [])->filter()->map(fn ($v, $k) => $k.': '.$v)->implode(' · '),
                    ])->all()" />
                @endif
            </x-ui.card>
        </div>

        {{-- ===== العمود الجانبي ===== --}}
        <div class="grid gap-4 min-w-0">

            <x-ui.card :title="__('الاشتراك')">
                <form method="POST" action="{{ url('/admin/tenants/'.$tenant->id.'/plan') }}">
                    @csrf @method('PUT')
                    <x-ui.field :label="__('الباقة')" for="plan_key" :error="$errors->first('plan_key')">
                        <x-ui.select name="plan_key" id="plan_key" :invalid="$errors->has('plan_key')">
                            @foreach($plans as $option)
                                <option value="{{ $option->key }}" @selected($option->key === $tenant->plan_key)>
                                    {{ $option->name[app()->getLocale()] ?? $option->name['ar'] ?? $option->key }}
                                    — {{ $option->priceIn($tenant->currency)?->format() ?? __('بلا سعر بهذه العملة') }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field :label="__('السبب')" for="plan_reason" :hint="__('يُقيَّد في السجلّ.')">
                        <x-ui.input name="reason" id="plan_reason" :placeholder="__('ترقية بطلب العميل…')" />
                    </x-ui.field>
                    <x-ui.button size="sm" type="submit" class="w-full">{{ __('حفظ الباقة') }}</x-ui.button>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('حالة الاشتراك')">
                @if($nextStatuses === [])
                    <p class="text-sm text-subtle">{{ __('لا انتقال متاح من الحالة الحالية.') }}</p>
                @else
                    <form method="POST" action="{{ url('/admin/tenants/'.$tenant->id.'/status') }}">
                        @csrf @method('PUT')
                        <x-ui.field :label="__('الحالة الجديدة')" for="status" :error="$errors->first('status')">
                            <x-ui.select name="status" id="status" :invalid="$errors->has('status')">
                                @foreach($nextStatuses as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field :label="__('السبب')" for="status_reason" :hint="__('يُقيَّد في السجلّ.')">
                            <x-ui.input name="reason" id="status_reason" :placeholder="__('تعثّر في السداد…')" />
                        </x-ui.field>
                        <x-ui.button size="sm" variant="secondary" type="submit" class="w-full">{{ __('تغيير الحالة') }}</x-ui.button>
                    </form>
                    <p class="text-2xs text-subtle mt-3">
                        {{ __('التعليق يقفل لوحة المشترك ولا يمحو بياناته. الأرشفة وحدها تُخفي الموقع عن الطلاب.') }}
                    </p>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('البيانات')">
                <x-ui.description-list :items="[
                    __('المعرّف')       => $tenant->id,
                    __('المالك')        => $tenant->owner_name ?? '—',
                    __('الهاتف')        => $tenant->owner_phone ?? '—',
                    __('الدولة والعملة') => $tenant->country.' · '.$tenant->currency,
                    __('اللغة')         => $tenant->locale,
                    __('المنطقة الزمنية') => $tenant->timezone ?? '—',
                    __('الثيم')         => $tenant->theme ?? '—',
                    __('تاريخ الاشتراك') => $tenant->created_at?->format('Y-m-d'),
                    __('تاريخ التجهيز')  => $tenant->provisioned_at?->format('Y-m-d') ?? __('لم يكتمل'),
                ]" />
            </x-ui.card>

            <x-ui.card :title="__('النطاقات')">
                @if($tenant->domains->isEmpty())
                    <p class="text-sm text-subtle">{{ __('لا نطاقات بعد.') }}</p>
                @else
                    <ul class="grid gap-2">
                        @foreach($tenant->domains as $domain)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <span class="font-mono text-xs truncate min-w-0">{{ $domain->domain }}</span>
                                @if($domain->is_primary)<x-ui.badge tone="primary">{{ __('أساسي') }}</x-ui.badge>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('الصحة')">
                <x-ui.description-list :items="[
                    __('قاعدة البيانات') => $health['database'] ? __('موجودة') : __('غير موجودة'),
                    __('النطاق')        => $health['domain'] ? __('مربوط') : __('غير مربوط'),
                    __('التجهيز')       => $health['provisioned'] ? __('مكتمل') : __('لم يكتمل'),
                ]" />
            </x-ui.card>
        </div>
    </div>
</div>
</x-layouts.super-admin>
