<x-layouts.admin :title="__('توليد أسئلة')" current="questions">
<div class="max-w-[820px]">

    <x-ui.page-header :title="__('توليد أسئلة من مادة')"
                      :subtitle="__('الصق فصلاً من الكتاب أو ملخّصك، واحصل على أسئلة تراجعها في دقائق بدل أن تكتبها في ساعة.')"
                      :back="url('/admin/questions')" />

    @error('material')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

    {{--
        الحدّ يُقال قبل العمل: النموذج يُخطئ، ومن لا يعرف ذلك
        يمتحن بأسئلةٍ لم يقرأها ثم يخسر ثقة طلابه.
    --}}
    <x-ui.alert tone="info" class="mb-6">
        {{ __('الأسئلة تدخل بنك الأسئلة وحده ولا تدخل امتحاناً إلا بيدك. راجعها: النموذج يخلط تفصيلاً أحياناً، أو يضع إجابتين صحيحتين، أو يسأل عمّا ليس في مادتك.') }}
    </x-ui.alert>

    <x-ui.card>
        <form method="POST" action="{{ url('/admin/ai/questions') }}" enctype="multipart/form-data" class="grid gap-4">
            @csrf

            <x-ui.field :label="__('المادة')" for="material" class="mb-0"
                        :hint="__('الصق النصّ هنا — كلّما كان أوضح كانت الأسئلة أدقّ.')">
                <x-ui.textarea id="material" name="material" rows="10" maxlength="40000">{{ old('material') }}</x-ui.textarea>
            </x-ui.field>

            <x-ui.field :label="__('أو ارفع ملفاً نصّياً')" for="file" class="mb-0"
                        :hint="__('txt أو md — وPDF يُنسخ نصّه ويُلصق أعلاه.')">
                <input id="file" type="file" name="file" accept=".txt,.md,text/plain"
                       class="w-full text-sm rounded-md border border-line-strong bg-surface px-3 py-2.5
                              file:me-3 file:rounded file:border-0 file:bg-surface-sunken file:px-3 file:py-1.5
                              file:text-xs file:font-semibold file:text-content">
            </x-ui.field>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field :label="__('عدد الأسئلة')" for="count" required class="mb-0">
                    <x-ui.input id="count" name="count" type="number" min="1" max="30" value="{{ old('count', 10) }}" required />
                </x-ui.field>

                <x-ui.field :label="__('المستوى')" for="difficulty" class="mb-0">
                    <select id="difficulty" name="difficulty"
                            class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                        <option value="easy">{{ __('سهل') }}</option>
                        <option value="medium" selected>{{ __('متوسط') }}</option>
                        <option value="hard">{{ __('صعب') }}</option>
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('التصنيف')" for="pool" class="mb-0">
                    <select id="pool" name="pool_id"
                            class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                        <option value="">{{ __('— بلا تصنيف —') }}</option>
                        @foreach($pools as $pool)
                            <option value="{{ $pool->id }}">{{ $pool->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <div>
                <x-ui.button type="submit">{{ __('ولّد الأسئلة') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>

</div>
</x-layouts.admin>
