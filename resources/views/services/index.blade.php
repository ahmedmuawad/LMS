<x-layouts.app :title="__('الخدمات')">
<x-site.header />

<main id="main" class="max-w-[1200px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('الخدمات')"
                      :subtitle="trans_choice('{0} لا خدمات بعد|{1} خدمة واحدة|{2} خدمتان|[3,10] :count خدمات|[11,*] :count خدمة', $services->total(), ['count' => $services->total()])" />

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
        <x-ui.field :label="__('ابحث')" for="q" class="mb-0 w-full sm:w-72">
            <x-ui.input name="q" id="q" type="search" value="{{ request('q') }}" :placeholder="__('اسم الخدمة…')" />
        </x-ui.field>
        <x-ui.field :label="__('القسم')" for="category" class="mb-0 w-full sm:w-56">
            <x-ui.select name="category" id="category">
                <option value="">{{ __('كل الأقسام') }}</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field :label="__('النوع')" for="type" class="mb-0 w-full sm:w-48">
            <x-ui.select name="type" id="type">
                <option value="">{{ __('كل الأنواع') }}</option>
                @foreach(App\Modules\Services\Models\Service::TYPES as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ __($label) }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.button size="sm" type="submit" class="h-11">{{ __('تصفية') }}</x-ui.button>
    </form>

    @if($services->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا خدمات مطابقة')">{{ __('جرّب كلمة أعمّ أو امسح الفلاتر.') }}</x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($services as $service)
                <x-services.card :service="$service" />
            @endforeach
        </div>

        @if($services->hasPages())
            <div class="mt-6">
                <x-ui.pagination :current="$services->currentPage()" :last="$services->lastPage()"
                                 :url="request()->fullUrlWithQuery(['page' => '']).''" />
            </div>
        @endif
    @endif
</main>

<x-site.footer />
</x-layouts.app>
