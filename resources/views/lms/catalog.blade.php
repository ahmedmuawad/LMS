<x-layouts.app :title="__('الكورسات')">
<x-site.header />

<main id="main" class="max-w-[1200px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('الكورسات')"
                      :subtitle="trans_choice('{0} لا كورسات بعد|{1} كورس واحد|{2} كورسان|[3,10] :count كورسات|[11,*] :count كورساً', $courses->total(), ['count' => $courses->total()])" />

    <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <x-ui.field :label="__('ابحث')" for="q" class="mb-0">
            <x-ui.input name="q" id="q" type="search" value="{{ request('q') }}" :placeholder="__('اسم الكورس…')" />
        </x-ui.field>

        <x-ui.field :label="__('القسم')" for="category" class="mb-0">
            <x-ui.select name="category" id="category">
                <option value="">{{ __('كل الأقسام') }}</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>

        <x-ui.field :label="__('المستوى')" for="level" class="mb-0">
            <x-ui.select name="level" id="level">
                <option value="">{{ __('كل المستويات') }}</option>
                @foreach($levels as $level)
                    <option value="{{ $level->id }}" @selected(request('level') == $level->id)>{{ $level->name }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>

        <div class="grid grid-cols-2 gap-2 items-end">
            <x-ui.field :label="__('الترتيب')" for="sort" class="mb-0">
                <x-ui.select name="sort" id="sort">
                    <option value="">{{ __('الأحدث') }}</option>
                    <option value="popular" @selected(request('sort') === 'popular')>{{ __('الأكثر طلاباً') }}</option>
                    <option value="rating" @selected(request('sort') === 'rating')>{{ __('الأعلى تقييماً') }}</option>
                    <option value="price" @selected(request('sort') === 'price')>{{ __('الأقل سعراً') }}</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.button type="submit" class="h-11">{{ __('تصفية') }}</x-ui.button>
        </div>
    </form>

    @if($courses->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا كورسات مطابقة')">
                {{ __('جرّب كلمة أعمّ، أو امسح الفلاتر لترى كل ما هو متاح.') }}
                <x-slot:action>
                    <x-ui.button size="sm" variant="secondary" :href="url('/courses')">{{ __('امسح الفلاتر') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($courses as $course)
                <x-lms.course-card :course="$course" />
            @endforeach
        </div>

        @if($courses->hasPages())
            <div class="mt-6">
                <x-ui.pagination :current="$courses->currentPage()" :last="$courses->lastPage()"
                                 :url="request()->fullUrlWithQuery(['page' => '']).''" />
            </div>
        @endif
    @endif
</main>

<x-site.footer />
</x-layouts.app>
