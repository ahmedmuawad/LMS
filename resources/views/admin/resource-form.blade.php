@php
    /** @var \App\Core\Admin\Resource $resource */
    $isEdit = $record !== null;
    $title  = $isEdit
        ? __('تعديل').' '.$resource->singularLabel()
        : __('إضافة').' '.$resource->singularLabel();
@endphp

<x-layouts.admin :title="$title" :current="$key">
<div class="max-w-3xl">

    <x-ui.page-header :title="$title" :back="url('/admin/'.$key)">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => $resource->label(), 'url' => url('/admin/'.$key)],
                ['label' => $isEdit ? __('تعديل') : __('إضافة')],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if($errors->any())
        @php $errorCount = count($errors->all()); @endphp
        <div class="mb-4">
            <x-ui.alert tone="danger" :title="trans_choice('{1} حقل واحد يحتاج تصحيحاً|{2} حقلان يحتاجان تصحيحاً|[3,10] :count حقول تحتاج تصحيحاً|[11,*] :count حقلاً يحتاج تصحيحاً', $errorCount, ['count' => $errorCount])">
                {{ __('راجع الحقول المميّزة بالأحمر أدناه.') }}
            </x-ui.alert>
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? url('/admin/'.$key.'/'.$record->getKey()) : url('/admin/'.$key) }}"
          x-data="{ dirty: false }" @change="dirty = true"
          @beforeunload.window="if (dirty) $event.preventDefault()">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="flex flex-col gap-4">
            @foreach($resource->form() as $section)
                <x-ui.card :title="$section->title" :subtitle="$section->getDescription()">
                    <div class="grid gap-x-4 sm:grid-cols-2">
                        @foreach($section->getFields() as $field)
                            @continue(! $field->showsOn($isEdit ? 'edit' : 'create'))
                            @php
                                $props = [
                                    'name'     => $field->name,
                                    'label'    => $field->getLabel(),
                                    'hint'     => $field->getHint(),
                                    'required' => $field->isRequired(),
                                    'value'    => $field->valueFor($record),
                                ] + $field->props();
                            @endphp
                            <div @class(['sm:col-span-2' => $field->getSpan() === 'full'])>
                                <x-dynamic-component :component="$field->component()"
                                                     :attributes="new Illuminate\View\ComponentAttributeBag($props)" />
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-2 mt-5 sticky bottom-0 bg-bg/95 backdrop-blur py-3">
            <x-ui.button type="submit" @click="dirty = false">
                {{ $isEdit ? __('حفظ التغييرات') : __('إضافة') }}
            </x-ui.button>
            <x-ui.button variant="ghost" :href="url('/admin/'.$key)">{{ __('إلغاء') }}</x-ui.button>

            @if($isEdit)
                <x-ui.button type="submit" variant="danger" form="delete-record" class="ms-auto">
                    {{ __('حذف') }}
                </x-ui.button>
            @endif
        </div>
    </form>

    @if($isEdit)
        <form id="delete-record" method="POST" action="{{ url('/admin/'.$key.'/'.$record->getKey()) }}"
              onsubmit="return confirm('{{ __('سيُحذف هذا السجل نهائياً. هل أنت متأكد؟') }}')">
            @csrf @method('DELETE')
        </form>
    @endif
</div>
</x-layouts.admin>
