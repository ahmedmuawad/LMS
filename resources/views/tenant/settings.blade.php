<x-layouts.admin :title="__('الإعدادات') . ' · ' . $group->label()" current="settings">
<div class="max-w-[1200px]" x-data="{ q: '' }">

    <x-ui.page-header :title="__('الإعدادات')"
                      :subtitle="__('كل خيار في منصّتك، مقسّماً على :count شاشة.', ['count' => count($groups)])">
        <x-slot:actions>
            <label class="relative w-full sm:w-72">
                <span class="sr-only">{{ __('ابحث في الإعدادات') }}</span>
                <x-ui.input x-model="q" type="search" :placeholder="__('ابحث في هذه الشاشة…')" />
            </label>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)] items-start">

        {{-- قائمة المجموعات: عمود على الشاشات الكبيرة، شريط أفقي على الصغيرة --}}
        <nav aria-label="{{ __('مجموعات الإعدادات') }}"
             class="flex lg:flex-col gap-1 overflow-x-auto lg:overflow-visible pb-2 lg:pb-0 -mx-1 px-1 lg:mx-0 lg:px-0">
            @foreach($groups as $item)
                <a href="{{ url('/admin/settings/'.$item->key()) }}"
                   @class([
                       'flex items-center gap-2 px-3 py-2 rounded-md text-sm whitespace-nowrap transition-colors shrink-0',
                       'bg-primary-subtle text-primary font-semibold' => $item->key() === $group->key(),
                       'text-muted hover:bg-surface-sunken hover:text-content' => $item->key() !== $group->key(),
                   ])
                   @if($item->key() === $group->key()) aria-current="page" @endif>
                    <span aria-hidden="true">{{ $item->icon() }}</span>
                    <span>{{ $item->label() }}</span>
                </a>
            @endforeach
        </nav>

        <form method="POST" action="{{ url('/admin/settings/'.$group->key()) }}" class="min-w-0">
            @csrf @method('PUT')

            @if($errors->any())
                <x-ui.alert tone="danger" :title="__('لم يُحفظ شيء — راجع الحقول المعلَّمة')" class="mb-4">
                    <ul class="list-disc list-inside grid gap-1 mt-1">
                        @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                </x-ui.alert>
            @endif

            @if($group->description())
                <p class="text-sm text-muted mb-4">{{ $group->description() }}</p>
            @endif

            <div class="grid gap-4">
                @foreach($group->sections() as $section)
                    <x-ui.card :title="$section->title" :subtitle="$section->getDescription()"
                               x-show="q === '' || $el.textContent.toLowerCase().includes(q.toLowerCase())">
                        <div class="grid gap-x-4 sm:grid-cols-2">
                            @foreach($section->getFields() as $field)
                                <div class="{{ $field->getSpan() === 'half' ? 'sm:col-span-1' : 'sm:col-span-2' }}">
                                    <x-dynamic-component
                                        :component="$field->component()"
                                        :attributes="new Illuminate\View\ComponentAttributeBag([
                                            'name'     => $field->name,
                                            'label'    => $field->getLabel(),
                                            'hint'     => $field->getHint(),
                                            'required' => $field->isRequired(),
                                            'value'    => $values[$field->name] ?? null,
                                            'isSet'    => $secrets[$field->name] ?? false,
                                        ] + $field->props())" />
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <p class="text-2xs text-subtle mt-4 mb-2">
                {{ __('لكل إعداد قيمة افتراضية عاقلة — منصّتك تعمل كاملة قبل أن تلمس هذه الشاشة.') }}
            </p>

            <div class="sticky bottom-0 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 bg-surface/95 backdrop-blur border-t border-line flex items-center gap-2">
                <x-ui.button type="submit">{{ __('حفظ التغييرات') }}</x-ui.button>
                <x-ui.button type="reset" variant="ghost">{{ __('تراجع') }}</x-ui.button>
            </div>
        </form>
    </div>
</div>
</x-layouts.admin>
