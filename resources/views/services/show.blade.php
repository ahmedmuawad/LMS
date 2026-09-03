@php
    $meta = app(App\Core\Seo\Seo::class)->forModel($service, [
        'breadcrumbs' => [
            ['name' => __('الخدمات'), 'url' => url('/services')],
            ['name' => (string) $service->title, 'url' => url('/services/'.$service->slug)],
        ],
    ]);
@endphp
<x-layouts.app :title="$service->title" :meta="$meta">
<x-site.header />

@php
    /** التقويم يصل مصفوفة تواريخ؛ Alpine يحتاجه شكلاً واحداً مسطّحاً */
    $days = collect($calendar)->map(fn (array $slots, string $date): array => [
        'date' => $date,
        'label' => \Illuminate\Support\Carbon::parse($date)->translatedFormat('l j F'),
        'slots' => array_values($slots),
    ])->values()->all();
@endphp

<main id="main" class="max-w-[1200px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.breadcrumb :items="[
        ['label' => __('الخدمات'), 'url' => url('/services')],
        ['label' => $service->title],
    ]" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_360px] mt-4 items-start">

        <div class="min-w-0 flex flex-col gap-6">
            @if($service->cover)
                <img src="{{ $service->cover->url() }}" alt="{{ $service->cover->alt ?? '' }}"
                     class="w-full rounded-lg bg-surface-sunken" width="{{ $service->cover->width }}" height="{{ $service->cover->height }}">
            @endif

            <header class="flex flex-col gap-2">
                @if($service->category)<span class="text-2xs text-subtle">{{ $service->category->name }}</span>@endif
                <h1 class="text-2xl sm:text-3xl font-bold leading-tight">{{ $service->title }}</h1>
                @if($service->excerpt)<p class="text-muted leading-relaxed">{{ $service->excerpt }}</p>@endif
            </header>

            @php
                $facts = [__('نوع الخدمة') => __(App\Modules\Services\Models\Service::TYPES[$service->type] ?? $service->type)];

                if ($service->type === 'appointment') {
                    $facts[__('مدة الجلسة')] = trans_choice('{1} دقيقة|{2} دقيقتان|[3,10] :count دقائق|[11,*] :count دقيقة', (int) $service->duration_minutes, ['count' => (int) $service->duration_minutes]);
                    $facts[__('مهلة الحجز')] = trans_choice('{1} ساعة|{2} ساعتان|[3,10] :count ساعات|[11,*] :count ساعة', (int) $service->lead_hours, ['count' => (int) $service->lead_hours]);
                } elseif ($service->delivery_days > 0) {
                    $facts[__('مدة التسليم')] = trans_choice('{1} يوم|{2} يومان|[3,10] :count أيام|[11,*] :count يوماً', (int) $service->delivery_days, ['count' => (int) $service->delivery_days]);
                }

                $facts[__('مكان التقديم')] = __(['online' => 'أونلاين', 'onsite' => 'حضورياً', 'both' => 'أونلاين أو حضورياً'][$service->location] ?? '—');
            @endphp

            <x-ui.description-list :items="$facts" class="surface-card px-5" />

            @if($service->description)
                <section class="surface-card p-5">
                    <h2 class="text-lg font-bold mb-3">{{ __('عن الخدمة') }}</h2>
                    <div class="leading-loose text-muted whitespace-pre-line">{{ $service->description }}</div>
                </section>
            @endif

            @if(filled($service->deliverables))
                <section class="surface-card p-5">
                    <h2 class="text-lg font-bold mb-3">{{ __('ما ستحصل عليه') }}</h2>
                    <ul class="flex flex-col gap-2">
                        @foreach($service->deliverables as $item)
                            <li class="flex items-start gap-2 text-sm text-muted">
                                <span class="text-success mt-0.5 shrink-0" aria-hidden="true">✔</span>
                                <span>{{ is_array($item) ? ($item[app()->getLocale()] ?? reset($item)) : $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($service->providers->isNotEmpty())
                <section class="surface-card p-5">
                    <h2 class="text-lg font-bold mb-3">{{ __('من يقدّمها') }}</h2>
                    <ul class="flex flex-wrap gap-4">
                        @foreach($service->providers->where('is_active', true) as $provider)
                            <li class="flex items-center gap-2">
                                <x-ui.avatar :name="$provider->name()" size="sm" />
                                <span class="text-sm font-semibold">{{ $provider->name() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        {{-- ---------- بطاقة الحجز ---------- --}}
        <aside class="min-w-0 lg:sticky lg:top-6">
            <div class="surface-card p-5 flex flex-col gap-4">
                <p class="font-mono text-2xl font-bold tabular">
                    {{ $service->needsQuote() ? __('بعرض سعر') : $service->price()->format() }}
                    @if($service->price_type === 'hourly')<span class="text-sm font-normal text-subtle">/ {{ __('ساعة') }}</span>@endif
                </p>

                @if($errors->has('booking'))
                    <x-ui.alert tone="danger">{{ $errors->first('booking') }}</x-ui.alert>
                @endif

                <form method="POST" action="{{ url('/services/'.$service->slug.'/book') }}"
                      class="flex flex-col gap-4"
                      x-data="{
                          days: {{ Js::from($days) }},
                          date: '{{ old('date') }}',
                          slot: '{{ old('starts_at') }}',
                          provider: '{{ old('provider_id') }}',
                          get slots() { return (this.days.find(d => d.date === this.date) || {}).slots || [] },
                          pick(date) { this.date = date; this.slot = ''; this.provider = '' },
                          choose(s) { this.slot = s.starts_at; this.provider = String(s.provider_id) },
                      }">
                    @csrf

                    @if($service->isBookable())
                        @if($days === [])
                            <x-ui.alert tone="warning" :title="__('لا مواعيد متاحة')">
                                {{ __('لا مواعيد شاغرة في المدى القادم. راسلنا لنرتّب موعداً.') }}
                            </x-ui.alert>
                        @else
                            <div>
                                <p class="text-sm font-semibold mb-2">{{ __('اختر اليوم') }}</p>
                                <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
                                    <template x-for="day in days" :key="day.date">
                                        <button type="button" @click="pick(day.date)"
                                                :class="date === day.date ? 'bg-primary text-primary-on border-transparent' : 'bg-surface border-line-strong hover:bg-surface-sunken'"
                                                class="shrink-0 min-h-11 px-3 py-2 rounded-md border text-xs font-semibold transition-colors"
                                                x-text="day.label"></button>
                                    </template>
                                </div>
                            </div>

                            <div x-show="date" x-cloak>
                                <p class="text-sm font-semibold mb-2">{{ __('اختر الموعد') }}</p>
                                <div class="grid grid-cols-3 gap-2">
                                    <template x-for="s in slots" :key="s.starts_at + '-' + s.provider_id">
                                        <button type="button" @click="choose(s)"
                                                :class="slot === s.starts_at && provider === String(s.provider_id)
                                                    ? 'bg-primary text-primary-on border-transparent'
                                                    : 'bg-surface border-line-strong hover:bg-surface-sunken'"
                                                class="min-h-11 rounded-md border font-mono text-xs tabular transition-colors"
                                                x-text="s.starts_at"></button>
                                    </template>
                                </div>
                            </div>

                            <input type="hidden" name="date" :value="date">
                            <input type="hidden" name="starts_at" :value="slot">
                            <input type="hidden" name="provider_id" :value="provider">
                        @endif
                    @endif

                    @guest
                        <x-ui.field :label="__('الاسم')" for="name" class="mb-0" required :error="$errors->first('name')">
                            <x-ui.input name="name" id="name" required maxlength="120" value="{{ old('name') }}" />
                        </x-ui.field>
                        <x-ui.field :label="__('البريد')" for="email" class="mb-0" required :error="$errors->first('email')">
                            <x-ui.input name="email" id="email" type="email" required value="{{ old('email') }}" />
                        </x-ui.field>
                    @endguest

                    <x-ui.field :label="__('رقم الهاتف')" for="phone" class="mb-0" :error="$errors->first('phone')">
                        <x-ui.input name="phone" id="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" />
                    </x-ui.field>

                    @foreach(($service->requirements ?? []) as $index => $requirement)
                        @php $label = is_array($requirement) ? ($requirement[app()->getLocale()] ?? reset($requirement)) : $requirement; @endphp
                        <x-ui.field :label="$label" for="intake-{{ $index }}" class="mb-0">
                            <x-ui.textarea name="intake[{{ $label }}]" id="intake-{{ $index }}" rows="2" />
                        </x-ui.field>
                    @endforeach

                    <x-ui.field :label="__('ملاحظات')" for="notes" class="mb-0">
                        <x-ui.textarea name="notes" id="notes" rows="3" :placeholder="__('أي تفاصيل تساعدنا…')" />
                    </x-ui.field>

                    <x-ui.button type="submit" size="lg" class="w-full"
                                 :disabled="$service->isBookable() && $days === []">
                        {{ $service->needsQuote() ? __('اطلب عرض سعر') : __('أكّد الحجز') }}
                    </x-ui.button>

                    @if($service->cancel_hours > 0)
                        <p class="text-2xs text-subtle text-center">
                            {{ __('إلغاء مجاني قبل الموعد بـ :hours ساعة.', ['hours' => $service->cancel_hours]) }}
                        </p>
                    @endif
                </form>
            </div>
        </aside>
    </div>
</main>

<x-site.footer />
</x-layouts.app>
