<x-layouts.super-admin :title="__('بوّابة واتساب')">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('بوّابة واتساب')"
                      :subtitle="__('خادمٌ واحد للمنصّة، ونسخةٌ لكل مشترك بمفتاحها — فتخرج رسائل كلٍّ من رقمه هو.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif

    @if($errors->any())
        <x-ui.alert tone="danger" :title="__('راجع ما يلي')" class="mb-5">
            <ul class="list-disc list-inside grid gap-1 mt-1">
                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    <x-ui.card :title="__('الخادم')" class="mb-6">
        <form method="POST" action="{{ url('/admin/whatsapp-gateway') }}" class="grid gap-4">
            @csrf @method('PUT')

            <x-ui.field :label="__('عنوان الخادم')" for="server_url" class="mb-0"
                        :hint="__('عنوان Evolution API كاملاً — مثل https://wa.example.com')">
                <x-ui.input id="server_url" name="server_url" type="url" dir="ltr"
                            value="{{ $server }}" placeholder="https://wa.example.com" />
            </x-ui.field>

            <x-ui.field :label="__('المفتاح العام')" for="global_key" class="mb-0"
                        :hint="$hasKey
                            ? __('محفوظ ومشفّر. اترك الحقل فارغاً للإبقاء عليه.')
                            : __('المفتاح الذي يُنشئ النُّسَخ ويحذفها. لا يخرج إلى المشتركين أبداً.')">
                <x-ui.input id="global_key" name="global_key" type="password" dir="ltr"
                            autocomplete="new-password"
                            placeholder="{{ $hasKey ? '••••••••' : '' }}" />
            </x-ui.field>

            <div><x-ui.button type="submit">{{ __('احفظ') }}</x-ui.button></div>
        </form>
    </x-ui.card>

    <x-ui.card :title="__('نُسَخ المشتركين')">
        @if($tenants->isEmpty())
            <x-ui.empty :title="__('لا مشتركين')" />
        @else
            <div class="grid gap-1.5">
                @foreach($tenants as $row)
                    <div class="flex flex-wrap items-center gap-3 py-2 border-b border-line last:border-0">
                        <span class="min-w-0 flex-1 text-sm truncate">
                            {{ $row['tenant']->name }}
                            <span class="text-subtle font-mono text-2xs">{{ $row['tenant']->slug }}</span>
                        </span>

                        @if($row['instance'] === '')
                            <x-ui.badge tone="neutral">{{ __('لم يربط بعد') }}</x-ui.badge>
                        @else
                            <x-ui.badge :tone="match($row['state']) {
                                'open' => 'success',
                                'connecting' => 'warning',
                                default => 'danger',
                            }">
                                {{ match($row['state']) {
                                    'open' => __('موصول'),
                                    'connecting' => __('بانتظار المسح'),
                                    'close' => __('مفصول'),
                                    default => __('لا يستجيب'),
                                } }}
                            </x-ui.badge>

                            @if($row['tenant']->wa_number)
                                <span class="font-mono text-2xs text-subtle">{{ $row['tenant']->wa_number }}</span>
                            @endif

                            {{--
                                الحذف للحالات المتعطّلة: نسخةٌ لا تستجيب
                                ولا يستطيع صاحبها إصلاحها من لوحته.
                            --}}
                            <form method="POST" action="{{ url('/admin/whatsapp-gateway/'.$row['tenant']->id.'/reset') }}"
                                  onsubmit="return confirm('{{ __('حذف النسخة يقطع رسائل هذا المشترك حتى يربط رقمه من جديد. متابعة؟') }}')">
                                @csrf
                                <x-ui.button size="sm" variant="danger" type="submit">{{ __('احذف النسخة') }}</x-ui.button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

</div>
</x-layouts.super-admin>
