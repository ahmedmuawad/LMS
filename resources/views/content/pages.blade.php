<x-layouts.admin :title="__('محرّر الصفحات')">

<x-ui.page-header :title="__('الصفحات')" :subtitle="__('كل صفحة تُبنى بكتل — لا محرّر نصّ حرّ.')">
    <x-slot:actions>
        <x-ui.button type="button" x-data @click="$dispatch('open-modal', 'new-page')">{{ __('صفحة جديدة') }}</x-ui.button>
    </x-slot:actions>
</x-ui.page-header>

@if(session('status'))
    <x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>
@endif

@if($errors->any())
    <x-ui.alert tone="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
@endif

@if($pages->isEmpty())
    <x-ui.card>
        <x-ui.empty :title="__('لا صفحات بعد')">{{ __('ابدأ بصفحة، ثم اسحب إليها الكتل.') }}</x-ui.empty>
    </x-ui.card>
@else
    <div class="surface-card overflow-x-auto">
        <table class="w-full text-sm min-w-[560px]">
            <thead class="bg-surface-sunken text-2xs text-subtle">
                <tr>
                    <th class="text-start font-semibold px-4 py-3">{{ __('العنوان') }}</th>
                    <th class="text-start font-semibold px-4 py-3">{{ __('الرابط') }}</th>
                    <th class="text-start font-semibold px-4 py-3">{{ __('الحالة') }}</th>
                    <th class="text-start font-semibold px-4 py-3">{{ __('الكتل') }}</th>
                    <th class="px-4 py-3"><span class="sr-only">{{ __('إجراءات') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @foreach($pages as $page)
                    <tr>
                        <td class="px-4 py-3 font-semibold">
                            {{ $page->title ?: __('بلا عنوان') }}
                            @if($page->is_system)<x-ui.badge tone="neutral" class="ms-1">{{ __('إلزامية') }}</x-ui.badge>@endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-muted">/{{ $page->slug }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge :tone="$page->isLive() ? 'success' : 'neutral'">
                                {{ __(App\Modules\Content\Models\Page::STATUSES[$page->status] ?? $page->status) }}
                            </x-ui.badge>
                        </td>
                        <td class="px-4 py-3 font-mono tabular text-muted">{{ count($page->blocks ?? []) }}</td>
                        <td class="px-4 py-3 text-end">
                            <x-ui.button as="a" :href="route('admin.page-builder.edit', ['id' => $page->id])" size="sm" variant="secondary">
                                {{ __('حرّر') }}
                            </x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($pages->hasPages())
        <div class="mt-6">
            <x-ui.pagination :current="$pages->currentPage()" :last="$pages->lastPage()"
                             :url="request()->fullUrlWithQuery(['page' => '']).''" />
        </div>
    @endif
@endif

<x-ui.modal name="new-page" :title="__('صفحة جديدة')">
    <form method="POST" action="{{ route('admin.page-builder.store') }}" class="flex flex-col gap-3">
        @csrf
        <x-ui.field :label="__('العنوان')" for="new-title" class="mb-0" required>
            <x-ui.input name="title" id="new-title" required maxlength="200" />
        </x-ui.field>
        <x-ui.field :label="__('الرابط')" for="new-slug" class="mb-0" required :hint="__('حروف وأرقام وشرطات فقط.')">
            <x-ui.input name="slug" id="new-slug" required maxlength="200" class="font-mono" />
        </x-ui.field>
        <x-ui.button type="submit" class="self-start">{{ __('أنشئ وافتح المحرّر') }}</x-ui.button>
    </form>
</x-ui.modal>

</x-layouts.admin>
