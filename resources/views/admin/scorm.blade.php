<x-layouts.admin :title="__('حزمة SCORM')" current="lessons">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('حزمة SCORM: :lesson', ['lesson' => $lesson->title])"
                      :subtitle="__('ارفع حزمة SCORM 1.2 أو 2004 — تُفكّ وتُشغَّل داخل المنصة، ويُتتبَّع تقدّم كل طالب فيها.')"
                      :back="url('/admin/lessons/'.$lesson->getKey().'/edit')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif
    @error('package')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

    <x-ui.card :title="$package ? __('استبدال الحزمة') : __('رفع حزمة')" class="mb-6">
        <form method="POST" action="{{ url('/admin/lessons/'.$lesson->getKey().'/scorm') }}"
              enctype="multipart/form-data" class="grid gap-4">
            @csrf

            <x-ui.field :label="__('ملف الحزمة')" for="pkg" required class="mb-0"
                        :hint="__('ملف ZIP يحتوي imsmanifest.xml — حتى ٥٠٠ ميجابايت.')">
                <input id="pkg" type="file" name="package" accept=".zip,application/zip" required
                       class="w-full text-sm rounded-md border border-line-strong bg-surface px-3 py-2.5
                              file:me-3 file:rounded file:border-0 file:bg-surface-sunken file:px-3 file:py-1.5
                              file:text-xs file:font-semibold file:text-content">
            </x-ui.field>

            @if($package)
                {{-- الاستبدال يُقال صراحةً: من رفع لا يتوقّع أن يُحذف شيء --}}
                <x-ui.alert tone="warning">
                    {{ __('سيحلّ الملف الجديد محلّ الحزمة الحالية وتُحذف ملفّاتها. وتقدّم الطلبة يبقى كما هو.') }}
                </x-ui.alert>
            @endif

            <div><x-ui.button type="submit">{{ $package ? __('استبدل') : __('ارفع') }}</x-ui.button></div>
        </form>
    </x-ui.card>

    @if(! $package)
        <x-ui.card>
            <x-ui.empty :title="__('لا حزمة لهذا الدرس')">
                {{ __('حزمة SCORM هي ما تُصدّره منصّات التأليف ومواد الناشرين — ارفعها كما هي.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-ui.card :title="__('الحزمة الحالية')" class="mb-6">
            <x-ui.description-list :items="[
                __('العنوان') => $package->title ?: __('بلا عنوان'),
                __('الإصدار') => 'SCORM '.$package->version,
                __('نقطة البداية') => $package->entry,
                __('الحجم') => $package->sizeLabel(),
            ]" />

            <div class="mt-4">
                <form method="POST" action="{{ url('/admin/lessons/'.$lesson->getKey().'/scorm') }}"
                      onsubmit="return confirm('{{ __('حذف الحزمة وملفّاتها؟') }}')">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" size="sm" variant="danger">{{ __('حذف الحزمة') }}</x-ui.button>
                </form>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('تقدّم الطلبة')">
            @if($package->states->isEmpty())
                <x-ui.empty :title="__('لم يفتحها أحد بعد')">
                    {{ __('يظهر هنا من فتحها وأين وصل وكم درجته.') }}
                </x-ui.empty>
            @else
                <div class="grid gap-1.5">
                    @foreach($package->states as $state)
                        <div class="flex flex-wrap items-center gap-3 py-2 border-b border-line last:border-0">
                            <span class="min-w-0 flex-1 text-sm truncate">{{ $state->user?->name ?? '—' }}</span>

                            <x-ui.badge :tone="$state->isComplete() ? 'success' : 'neutral'">
                                {{ $state->statusLabel() }}
                            </x-ui.badge>

                            @if($state->score_raw !== null)
                                <span class="font-mono text-xs tabular">{{ rtrim(rtrim(number_format($state->score_raw, 1), '0'), '.') }}%</span>
                            @endif

                            <span class="font-mono text-2xs text-subtle tabular">{{ $state->timeLabel() }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    @endif

</div>
</x-layouts.admin>
