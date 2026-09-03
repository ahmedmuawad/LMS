<x-layouts.admin :title="__('الوسائط')">

<x-ui.page-header :title="__('مكتبة الوسائط')"
                  :subtitle="trans_choice('{0} لا ملفات|{1} ملف واحد|{2} ملفان|[3,10] :count ملفات|[11,*] :count ملفاً', $media->total(), ['count' => $media->total()])" />

@if(session('status'))
    <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
@endif

@if($errors->has('files'))
    <x-ui.alert tone="danger" class="mb-4">{{ $errors->first('files') }}</x-ui.alert>
@endif

<form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data"
      class="surface-card p-4 flex flex-col sm:flex-row items-stretch sm:items-end gap-3 mb-6">
    @csrf
    <x-ui.field :label="__('ارفع ملفات')" for="files" class="mb-0 flex-1 min-w-0">
        <x-ui.file-upload name="files[]" id="files" multiple />
    </x-ui.field>
    <x-ui.field :label="__('المجلد')" for="folder" class="mb-0 w-full sm:w-48">
        <x-ui.input name="folder" id="folder" placeholder="library" class="font-mono" />
    </x-ui.field>
    <x-ui.button type="submit" class="w-full sm:w-auto sm:shrink-0 h-11">{{ __('ارفع') }}</x-ui.button>
</form>

<form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <x-ui.field :label="__('ابحث')" for="q" class="mb-0 w-full sm:w-72">
        <x-ui.input name="q" id="q" type="search" value="{{ request('q') }}" :placeholder="__('اسم الملف…')" />
    </x-ui.field>
    <x-ui.field :label="__('النوع')" for="kind" class="mb-0 w-full sm:w-48">
        <x-ui.select name="kind" id="kind">
            <option value="">{{ __('الكل') }}</option>
            <option value="image" @selected(request('kind') === 'image')>{{ __('صور') }}</option>
            <option value="file" @selected(request('kind') === 'file')>{{ __('ملفات') }}</option>
        </x-ui.select>
    </x-ui.field>
    <x-ui.button size="sm" type="submit" class="h-11">{{ __('تصفية') }}</x-ui.button>
</form>

@if($media->isEmpty())
    <x-ui.card>
        <x-ui.empty :title="__('المكتبة فارغة')">{{ __('ارفع أول ملف لتبدأ.') }}</x-ui.empty>
    </x-ui.card>
@else
    <ul class="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
        @foreach($media as $file)
            <li class="surface-card overflow-hidden flex flex-col" x-data="{ editing: false }">
                <div class="aspect-square bg-surface-sunken relative">
                    @if($file->isImage())
                        <img src="{{ $file->url() }}" alt="{{ $file->alt ?? $file->name }}"
                             class="size-full object-cover" loading="lazy">
                    @else
                        <span class="absolute inset-0 grid place-items-center text-3xl text-subtle" aria-hidden="true">◲</span>
                    @endif

                    @if($file->isImage() && blank($file->alt))
                        <span class="absolute top-2 start-2 text-2xs px-2 py-1 rounded-md bg-warning-subtle text-warning font-semibold">
                            {{ __('بلا نص بديل') }}
                        </span>
                    @endif
                </div>

                <div class="p-3 flex flex-col gap-2 flex-1 min-w-0">
                    <p class="text-xs font-semibold truncate" title="{{ $file->name }}">{{ $file->name }}</p>
                    <p class="text-2xs text-subtle font-mono">{{ $file->humanSize() }}@if($file->width) · {{ $file->width }}×{{ $file->height }}@endif</p>

                    <div class="flex items-center gap-1 mt-auto pt-1">
                        <button type="button" @click="editing = ! editing"
                                class="flex-1 min-h-11 sm:min-h-9 rounded-md text-2xs font-semibold border border-line-strong hover:bg-surface-sunken transition-colors">
                            {{ __('النص البديل') }}
                        </button>
                        <form method="POST" action="{{ route('admin.media.destroy', ['id' => $file->id]) }}"
                              onsubmit="return confirm('{{ __('حذف هذا الملف نهائياً؟') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="size-11 sm:size-9 grid place-items-center rounded-md text-danger hover:bg-danger-subtle transition-colors"
                                    aria-label="{{ __('حذف') }}">✕</button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('admin.media.update', ['id' => $file->id]) }}"
                          x-show="editing" x-cloak class="flex flex-col gap-2 pt-2 border-t border-default">
                        @csrf
                        @method('PUT')
                        @foreach(array_keys(config('locales.supported')) as $locale)
                            <x-ui.input name="alt[{{ $locale }}]" :placeholder="__('نص بديل').' ('.$locale.')'"
                                        value="{{ $file->getTranslation('alt', $locale) }}" />
                        @endforeach
                        <x-ui.button type="submit" size="sm">{{ __('حفظ') }}</x-ui.button>
                    </form>
                </div>
            </li>
        @endforeach
    </ul>

    @if($media->hasPages())
        <div class="mt-6">
            <x-ui.pagination :current="$media->currentPage()" :last="$media->lastPage()"
                             :url="request()->fullUrlWithQuery(['page' => '']).''" />
        </div>
    @endif
@endif

</x-layouts.admin>
