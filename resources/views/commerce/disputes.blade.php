<x-layouts.admin :title="__('النزاعات')" current="disputes">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('النزاعات')"
                      :subtitle="__('حين يشكو المشتري لبنكه — والمهلة أيامٌ معدودة.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    {{--
        الفرق عن الاسترداد يُقال، لا يُفترَض معروفاً.

        الاسترداد قرار المشترك؛ والنزاع قرار البنك: المال سُحب فعلاً،
        ومن لم يردّ بالدليل خسِر المبلغ ورسمَ النزاع فوقه.
    --}}
    <x-ui.alert tone="info" class="mb-5">
        <p class="text-sm leading-relaxed">
            {{ __('النزاع غير الاسترداد: المال سُحب من حسابك فعلاً، والبنك يمهلك لترسل دليلك. ودليلُك عندنا — سجلّ دخول الطالب ومشاهداته ودرجاته.') }}
        </p>
    </x-ui.alert>

    @if($rows->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا نزاعات مفتوحة')">
                {{ $resolved > 0
                    ? __(':n نزاعاً مُحسَماً من قبل.', ['n' => $resolved])
                    : __('لم يفتح أحدٌ نزاعاً على دفعاتك.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-3">
            @foreach($rows as $dispute)
                <div @class([
                    'surface-card p-4',
                    'border-danger' => $dispute->isOverdue(),
                    'border-warning' => ! $dispute->isOverdue() && $dispute->isUrgent(),
                ])>
                    <div class="flex flex-wrap items-start gap-x-4 gap-y-2 mb-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold flex flex-wrap items-center gap-2">
                                {{ $dispute->amount()->format() }}

                                @if($dispute->order)
                                    <a href="{{ url('/admin/orders/'.$dispute->order->id) }}"
                                       class="text-xs text-primary hover:underline font-mono" dir="ltr">
                                        {{ $dispute->order->number }}
                                    </a>
                                @endif

                                <x-ui.badge tone="neutral">{{ $dispute->gateway }}</x-ui.badge>
                            </p>

                            @if($dispute->reason)
                                <p class="text-xs text-muted mt-1">{{ $dispute->reason }}</p>
                            @endif

                            {{--
                                المهلة هي سبب وجود هذه الشاشة كلّها،
                                فتُعرض كبيرةً وبلونٍ يتغيّر باقترابها.
                            --}}
                            @if($dispute->due_by)
                                <p @class([
                                    'text-xs font-semibold mt-1.5',
                                    'text-danger' => $dispute->isOverdue(),
                                    'text-warning' => ! $dispute->isOverdue() && $dispute->isUrgent(),
                                    'text-subtle' => ! $dispute->isUrgent(),
                                ])>
                                    {{ $dispute->isOverdue()
                                        ? __('فاتت المهلة — :when', ['when' => $dispute->due_by->diffForHumans()])
                                        : __('المهلة تنتهي :when', ['when' => $dispute->due_by->diffForHumans()]) }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.disputes.update', $dispute->id) }}" class="grid gap-3">
                        @csrf @method('PUT')

                        <x-ui.field :label="__('ما سترسله للبنك')" :for="'ev-'.$dispute->id" class="mb-0"
                                    :hint="__('اذكر تاريخ الدخول والدروس التي شاهدها ودرجاته — وهو ما يُقنع البنك.')">
                            <x-ui.textarea :id="'ev-'.$dispute->id" name="evidence" rows="3">{{ $dispute->evidence }}</x-ui.textarea>
                        </x-ui.field>

                        <div class="flex flex-wrap items-end gap-3">
                            <x-ui.field :label="__('الحالة')" :for="'st-'.$dispute->id" class="mb-0 flex-1 min-w-[180px]">
                                <x-ui.select :id="'st-'.$dispute->id" name="status">
                                    @foreach($statuses as $key => $label)
                                        <option value="{{ $key }}" @selected($dispute->status === $key)>{{ $label }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>

                            <x-ui.button type="submit" size="sm">{{ __('احفظ') }}</x-ui.button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="mt-5">{{ $rows->links() }}</div>
    @endif

    {{--
        من أين تصل النزاعات — بصراحة.

        وادّعاءُ قراءةٍ تلقائية لا نضمنها أسوأ من الاعتراف: من يظنّها
        تعمل لا يفتح هذه الشاشة أصلاً، وتفوته المهلة.
    --}}
    <x-ui.card :title="__('من أين تصل')" class="mt-5">
        <p class="text-sm text-muted leading-relaxed">
            {{ __('Stripe تُخطرنا تلقائياً، فيظهر النزاع هنا وحده ويصلك إشعار.') }}
        </p>
        <p class="text-sm text-muted leading-relaxed mt-2">
            {{ __('أمّا Paymob وفوري فتُخطرانك بالبريد — سجّل النزاع هنا حين يصلك، لتتابع مهلته ودليله في مكانٍ واحد.') }}
        </p>
    </x-ui.card>

</div>
</x-layouts.admin>
