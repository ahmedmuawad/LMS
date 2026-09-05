<x-layouts.admin :title="__('النسخ الاحتياطية')" current="backups">
<div class="max-w-[760px]">

    <x-ui.page-header :title="__('النسخ الاحتياطية')"
                      :subtitle="__('بياناتك تُنسَخ كل ليلة — وهنا تراها وتنزّلها.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @error('backup')<x-ui.alert tone="warning" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <div class="surface-card p-4 mb-5 flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold">{{ __('اطلب نسخةً الآن') }}</p>
            <p class="text-xs text-muted mt-1 leading-relaxed">
                {{ __('قبل تعديلٍ كبير — استيراد بيانات أو حذف مجموعة. مرّة كل :n دقيقة.', ['n' => $cooldown]) }}
            </p>
        </div>

        <form method="POST" action="{{ route('admin.backups.store') }}" class="shrink-0">
            @csrf
            <x-ui.button type="submit" :disabled="$availableIn > 0">
                {{ $availableIn > 0
                    ? __('بعد :n دقيقة', ['n' => (int) ceil($availableIn / 60)])
                    : __('اطلب نسخة') }}
            </x-ui.button>
        </form>
    </div>

    @if($backups === [])
        <x-ui.card>
            <x-ui.empty :title="__('لا نسخ بعد')">
                {{ __('تُؤخذ النسخة الأولى الليلة. أو اطلب واحدةً الآن من الزرّ أعلاه.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-2">
            @foreach($backups as $backup)
                <div class="surface-card p-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">{{ display_date($backup['date'], 'l j F Y') }}</p>
                        <p class="text-2xs text-subtle font-mono tabular mt-0.5">
                            {{ $backup['at'] }} · {{ $backup['size'] }}
                        </p>
                    </div>

                    <x-ui.button as="a" size="sm" variant="secondary"
                                 :href="route('admin.backups.download', $backup['date'])">
                        {{ __('نزّل') }}
                    </x-ui.button>
                </div>
            @endforeach
        </div>
    @endif

    {{--
        ما تحتويه النسخة يُقال صراحةً.

        من ينزّل ملفّاً باسم «نسخة احتياطية» يظنّه يشمل الفيديوهات
        والمرفقات؛ وهي في التخزين لا في القاعدة. واكتشافُ ذلك وقت
        الحاجة إليها أسوأ وقت.
    --}}
    <x-ui.card :title="__('ماذا في النسخة')" class="mt-5">
        <p class="text-sm text-muted leading-relaxed">
            {{ __('قاعدة بياناتك كاملة: الطلبة والكورسات والدرجات والحضور والطلبات والفواتير.') }}
        </p>
        <p class="text-sm text-warning leading-relaxed mt-2">
            {{ __('ولا تشمل الملفّات المرفوعة — الفيديوهات والصور والمرفقات محفوظة ومنسوخة عندنا على حدة.') }}
        </p>
    </x-ui.card>

</div>
</x-layouts.admin>
