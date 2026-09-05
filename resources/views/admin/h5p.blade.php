<x-layouts.admin :title="__('محتوى تفاعلي')" current="lessons">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('محتوى تفاعلي: :lesson', ['lesson' => $lesson->title])"
                      :subtitle="__('ارفع ملفّ H5P كما صدّرته من أداة التأليف — يُشغَّل داخل المنصة وتُسجَّل نتائج الطلبة فيه.')"
                      :back="url('/admin/lessons/'.$lesson->getKey().'/edit')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif
    @error('package')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

    <x-ui.card :title="$package ? __('استبدال الحزمة') : __('رفع حزمة')" class="mb-6">
        <form method="POST" action="{{ url('/admin/lessons/'.$lesson->getKey().'/h5p') }}"
              enctype="multipart/form-data" class="grid gap-4">
            @csrf

            <x-ui.field :label="__('ملف الحزمة')" for="pkg" required class="mb-0"
                        :hint="__('ملفّ بامتداد ‎.h5p‎ — تُصدّره من h5p.org أو Lumi أو أي أداة تأليف. حتى ٥٠٠ ميجابايت.')">
                <input id="pkg" type="file" name="package" accept=".h5p,.zip" required
                       class="w-full text-sm rounded-md border border-line-strong bg-surface px-3 py-2.5
                              file:me-3 file:rounded file:border-0 file:bg-surface-sunken file:px-3 file:py-1.5
                              file:text-xs file:font-semibold file:text-content">
            </x-ui.field>

            @if($package)
                {{-- الاستبدال يُقال صراحةً: من رفع لا يتوقّع أن يُحذف شيء --}}
                <x-ui.alert tone="warning">
                    {{ __('سيحلّ الملف الجديد محلّ الحزمة الحالية وتُحذف ملفّاتها. ونتائج الطلبة تبقى كما هي.') }}
                </x-ui.alert>
            @endif

            <div><x-ui.button type="submit">{{ $package ? __('استبدل') : __('ارفع') }}</x-ui.button></div>
        </form>
    </x-ui.card>

    @if(! $package)
        <x-ui.card>
            <x-ui.empty :title="__('لا حزمة لهذا الدرس')">
                {{ __('المحتوى التفاعلي: فيديو فيه أسئلة، بطاقات مراجعة، سحبٌ وإفلات، عرضٌ متفرّع. تُؤلّفه في أداةٍ خارجية وتُصدّره ملفّاً واحداً، وترفعه هنا.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-ui.card :title="__('الحزمة الحالية')" class="mb-6">
            <x-ui.description-list :items="[
                __('العنوان') => $package->title ?: __('بلا عنوان'),
                __('نوع المحتوى') => $package->kindLabel() ?: __('غير معروف'),
                __('الحجم') => $package->sizeLabel(),
            ]" />

            <div class="mt-4">
                <form method="POST" action="{{ url('/admin/lessons/'.$lesson->getKey().'/h5p') }}"
                      onsubmit="return confirm('{{ __('حذف الحزمة وملفّاتها؟') }}')">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" size="sm" variant="danger">{{ __('حذف الحزمة') }}</x-ui.button>
                </form>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('نتائج الطلبة')">
            @if($results->isEmpty())
                <x-ui.empty :title="__('لم يفتحها أحد بعد')">
                    {{ __('يظهر هنا من أتمّها وكم درجته ومتى — تصل النتيجة من المحتوى نفسه حين ينتهي منه الطالب.') }}
                </x-ui.empty>
            @else
                <div class="grid gap-1.5">
                    @foreach($results as $row)
                        <div class="flex flex-wrap items-center gap-3 py-2 border-b border-line last:border-0">
                            <span class="min-w-0 flex-1 text-sm truncate">{{ $row->user?->name ?? '—' }}</span>

                            <x-ui.badge :tone="$row->result_success === false ? 'danger' : ($row->result_completion ? 'success' : 'neutral')">
                                {{ $row->verbLabel() }}
                            </x-ui.badge>

                            @if($row->result_score !== null)
                                <span class="font-mono text-xs tabular">{{ rtrim(rtrim(number_format($row->result_score, 1), '0'), '.') }}%</span>
                            @endif

                            <span class="font-mono text-2xs text-subtle tabular">
                                {{ $row->stored_at?->translatedFormat('j M · H:i') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    @endif

</div>
</x-layouts.admin>
