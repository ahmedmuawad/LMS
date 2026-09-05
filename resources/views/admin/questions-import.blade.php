<x-layouts.admin :title="__('استيراد أسئلة')" current="questions">
<div class="max-w-[820px]">

    <x-ui.page-header :title="__('استيراد أسئلة من ملفّ')"
                      :subtitle="__('ألف سؤال في ملفّ إكسل تدخل بنكك في دقيقة، بدل إدخالها واحداً واحداً.')"
                      :back="url('/admin/questions')" />

    @error('file')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror
    @error('csv')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

    <x-ui.card :title="__('ابدأ بالقالب')" class="mb-6">
        <p class="text-muted text-sm leading-relaxed mb-4">
            {{ __('نزّل القالب، وافتحه بإكسل أو Google Sheets، واملأ أسئلتك مكان الأمثلة، ثم احفظه CSV وارفعه هنا. لا تغيّر أسماء الأعمدة.') }}
        </p>

        <x-ui.button variant="secondary" :href="url('/admin/questions/import/template')">
            {{ __('نزّل القالب') }}
        </x-ui.button>

        <div class="mt-5 pt-5 border-t border-line">
            <h4 class="text-sm font-bold mb-3">{{ __('الأعمدة') }}</h4>

            <dl class="grid gap-2 text-sm">
                @foreach([
                    'النوع' => 'اختيار واحد · اختيار متعدّد · صح وخطأ · أكمل الفراغ · إجابة قصيرة · مقالي',
                    'السؤال' => 'نصّ السؤال كما يقرؤه الطالب.',
                    'الخيارات' => 'للأسئلة ذات الخيارات — يُفصَل بينها بعلامة | لا بفاصلة.',
                    'الإجابة' => 'نصّ الخيار الصحيح لا حرفه. وفي «صح وخطأ» اكتب: صح أو خطأ. وفي «أكمل الفراغ» يمكن قبول أكثر من صيغة بـ |.',
                    'الشرح' => 'يظهر للطالب بعد التسليم. اتركه فارغاً إن لم ترد.',
                    'الصعوبة' => 'سهل · متوسط · صعب — تُستعمل في السحب العشوائي من البنك.',
                    'الدرجة' => 'رقم. الافتراضي واحد.',
                ] as $name => $hint)
                    <div class="grid sm:grid-cols-[110px_1fr] gap-1 sm:gap-3 py-1.5 border-b border-line last:border-0">
                        <dt class="font-semibold shrink-0">{{ $name }}</dt>
                        <dd class="text-muted leading-relaxed">{{ $hint }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </x-ui.card>

    <x-ui.card :title="__('ارفع الملفّ')">
        <form method="POST" action="{{ url('/admin/questions/import') }}"
              enctype="multipart/form-data" class="grid gap-4">
            @csrf

            <x-ui.field :label="__('ملفّ CSV')" for="file" class="mb-0"
                        :hint="__('حتى ٢٠٠٠ سؤال في الملفّ الواحد.')">
                <input id="file" type="file" name="file" accept=".csv,text/csv,text/plain"
                       class="w-full text-sm rounded-md border border-line-strong bg-surface px-3 py-2.5
                              file:me-3 file:rounded file:border-0 file:bg-surface-sunken file:px-3 file:py-1.5
                              file:text-xs file:font-semibold file:text-content">
            </x-ui.field>

            <x-ui.field :label="__('أو الصق المحتوى')" for="csv" class="mb-0"
                        :hint="__('انسخ من إكسل والصق هنا مباشرةً — بالترويسة.')">
                <x-ui.textarea id="csv" name="csv" rows="6" dir="ltr"
                               class="font-mono text-xs">{{ old('csv') }}</x-ui.textarea>
            </x-ui.field>

            <x-ui.field :label="__('التصنيف')" for="pool" class="mb-0"
                        :hint="__('كل الأسئلة المستوردة تدخل هذا التصنيف.')">
                <select id="pool" name="pool_id"
                        class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                    <option value="">{{ __('— بلا تصنيف —') }}</option>
                    @foreach($pools as $pool)
                        <option value="{{ $pool->id }}">{{ $pool->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <div><x-ui.button type="submit">{{ __('استورد') }}</x-ui.button></div>
        </form>
    </x-ui.card>

</div>
</x-layouts.admin>
