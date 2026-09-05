<x-layouts.admin :title="__('مرفقات الدرس')" current="lessons">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('مرفقات: :lesson', ['lesson' => $lesson->title])"
                      :subtitle="__('ملفّات PDF أو Word يقرؤها الطالب داخل المنصة — لا برابط يُنسَخ.')"
                      :back="url('/admin/lessons/'.$lesson->getKey().'/edit')" />

    @if(session('status'))
        <x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>
    @endif

    @error('file')
        <x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.card :title="__('إضافة مرفق')" class="mb-6">
        <form method="POST" action="{{ url('/admin/lessons/'.$lesson->getKey().'/attachments') }}"
              enctype="multipart/form-data" class="grid gap-4">
            @csrf

            <x-ui.field :label="__('الملف')" for="att-file" required
                        :hint="__('PDF أو Word — حتى ٥٠ ميجابايت.')">
                <input id="att-file" type="file" name="file" required
                       accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                       class="w-full text-sm rounded-md border border-line-strong bg-surface px-3 py-2.5
                              file:me-3 file:rounded file:border-0 file:bg-surface-sunken file:px-3 file:py-1.5
                              file:text-xs file:font-semibold file:text-content">
            </x-ui.field>

            <x-ui.field :label="__('اسم المرفق')" for="att-title"
                        :hint="__('اتركه فارغاً ليُستعمل اسم الملف.')">
                <x-ui.input id="att-title" name="title" maxlength="150"
                            :placeholder="__('مثال: ورقة مراجعة الوحدة الأولى')" />
            </x-ui.field>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex items-start gap-2.5 p-3 rounded-lg border border-line-strong cursor-pointer">
                    <input type="checkbox" name="is_downloadable" value="1" class="mt-0.5 accent-[var(--sem-primary)]">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold">{{ __('يسمح بالتنزيل') }}</span>
                        <span class="block text-xs text-muted leading-relaxed mt-0.5">
                            {{ __('الملف المنزَّل يخرج من حمايتك نهائياً — أَتِحه لما يُراد طبعه فقط.') }}
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-2.5 p-3 rounded-lg border border-line-strong cursor-pointer">
                    <input type="checkbox" name="watermark" value="1" checked class="mt-0.5 accent-[var(--sem-primary)]">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold">{{ __('علامة مائية باسم الطالب') }}</span>
                        <span class="block text-xs text-muted leading-relaxed mt-0.5">
                            {{ __('اسمه ورقمه ووقت الفتح على كل نسخة — فمن يصوّر يُعرَف.') }}
                        </span>
                    </span>
                </label>
            </div>

            <div>
                <x-ui.button type="submit">{{ __('رفع المرفق') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    @if($attachments->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا مرفقات لهذا الدرس')">
                {{ __('ارفع ورقة مراجعة أو ملخّصاً ليقرأه طلابك داخل المنصة.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-3">
            @foreach($attachments as $attachment)
                @php
                    $opens = $attachment->views->where('action', 'view')->count();
                    $downloads = $attachment->views->where('action', 'download')->count();
                @endphp

                <div class="surface-card p-4 grid gap-3">
                    <div class="flex flex-wrap items-start gap-x-4 gap-y-2">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm truncate">{{ $attachment->name() }}</p>
                            <p class="text-xs text-muted font-mono tabular mt-0.5">
                                {{ $attachment->kindLabel() }} · {{ $attachment->sizeLabel() }}
                            </p>

                            {{-- السجلّ هو الحماية الحقيقية: من فتح وكم مرة --}}
                            <p class="text-2xs text-subtle mt-1.5 font-mono tabular">
                                {{ __(':opens فتحة', ['opens' => $opens]) }}
                                @if($attachment->is_downloadable)
                                    · {{ __(':n تنزيل', ['n' => $downloads]) }}
                                @endif
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2 shrink-0">
                            <x-ui.button size="sm" variant="secondary"
                                         :href="url('/attachments/'.$attachment->getKey())">{{ __('معاينة') }}</x-ui.button>

                            <form method="POST"
                                  action="{{ url('/admin/lessons/'.$lesson->getKey().'/attachments/'.$attachment->getKey()) }}"
                                  onsubmit="return confirm('{{ __('حذف هذا المرفق؟') }}')">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" size="sm" variant="danger">{{ __('حذف') }}</x-ui.button>
                            </form>
                        </div>
                    </div>

                    <form method="POST"
                          action="{{ url('/admin/lessons/'.$lesson->getKey().'/attachments/'.$attachment->getKey()) }}"
                          class="flex flex-wrap items-end gap-3 pt-3 border-t border-line">
                        @csrf @method('PUT')

                        <div class="min-w-[180px] flex-1">
                            <x-ui.input name="title" :value="$attachment->title" maxlength="150"
                                        :placeholder="__('اسم المرفق')" />
                        </div>

                        <label class="flex items-center gap-2 text-xs min-h-11">
                            <input type="checkbox" name="is_downloadable" value="1"
                                   @checked($attachment->is_downloadable) class="accent-[var(--sem-primary)]">
                            {{ __('تنزيل') }}
                        </label>

                        <label class="flex items-center gap-2 text-xs min-h-11">
                            <input type="checkbox" name="watermark" value="1"
                                   @checked($attachment->watermark) class="accent-[var(--sem-primary)]">
                            {{ __('علامة مائية') }}
                        </label>

                        <x-ui.button type="submit" size="sm" variant="secondary">{{ __('حفظ') }}</x-ui.button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

</div>
</x-layouts.admin>
