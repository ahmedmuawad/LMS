<x-layouts.super-admin :title="__('بيانات التحصيل')" current="billing-settings">
<div class="max-w-[900px]">

    <x-ui.page-header :title="__('بيانات التحصيل')"
                      :subtitle="__('كيف تصلك أموال الاشتراكات. ما اكتملت بياناته وفُعِّل يظهر للعميل في شاشة الدفع — وما سواه لا يُعرض.')" />

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    @if($errors->any())
        <x-ui.alert tone="danger" :title="__('راجع البيانات')" class="mb-4">
            <ul class="grid gap-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ url('/admin/billing-settings') }}" class="grid gap-4">
        @csrf
        @method('PUT')

        @foreach($methods as $key => $meta)
            @php $enabled = (bool) ($values[$key.'.enabled'] ?? false); @endphp

            <x-ui.card :title="__($meta['label'])" :subtitle="__($meta['hint'] ?? '')">
                <x-slot:actions>
                    @if($ready[$key])
                        <x-ui.badge tone="success">{{ __('جاهزة للعرض') }}</x-ui.badge>
                    @elseif($enabled)
                        {{-- مفعّلة وبياناتها ناقصة: لا تُعرض للعميل، ونقول ذلك بدل الصمت --}}
                        <x-ui.badge tone="warning">{{ __('بياناتها ناقصة') }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral">{{ __('مطفأة') }}</x-ui.badge>
                    @endif
                </x-slot:actions>

                <x-ui.checkbox :name="$key.'_enabled'" value="1" :checked="$enabled" class="mb-4">
                    {{ __('اعرض هذه الطريقة للعملاء') }}
                </x-ui.checkbox>

                <div class="grid gap-x-4 sm:grid-cols-2">
                    @foreach($meta['fields'] as $field => $spec)
                        <x-ui.field :label="__($spec['label'])" :for="$key.'_'.$field"
                                    :hint="isset($spec['hint']) ? __($spec['hint']) : null"
                                    :error="$errors->first($key.'_'.$field)">
                            <x-ui.input :id="$key.'_'.$field" :name="$key.'_'.$field"
                                        :value="old($key.'_'.$field, $values[$key.'.'.$field] ?? '')"
                                        dir="auto" :invalid="$errors->has($key.'_'.$field)" />
                        </x-ui.field>
                    @endforeach

                    @foreach($shared as $field => $spec)
                        <div @class(['sm:col-span-2' => ($spec['type'] ?? '') === 'textarea'])>
                            <x-ui.field :label="__($spec['label'])" :for="$key.'_'.$field"
                                        :hint="isset($spec['hint']) ? __($spec['hint']) : null"
                                        :error="$errors->first($key.'_'.$field)">
                                @if(($spec['type'] ?? '') === 'textarea')
                                    <x-ui.textarea :id="$key.'_'.$field" :name="$key.'_'.$field" rows="2"
                                                   :invalid="$errors->has($key.'_'.$field)">{{ old($key.'_'.$field, $values[$key.'.'.$field] ?? '') }}</x-ui.textarea>
                                @else
                                    <x-ui.input :id="$key.'_'.$field" :name="$key.'_'.$field"
                                                :value="old($key.'_'.$field, $values[$key.'.'.$field] ?? '')"
                                                :invalid="$errors->has($key.'_'.$field)" />
                                @endif
                            </x-ui.field>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach

        <div class="flex flex-wrap gap-2 sticky bottom-0 bg-bg/95 backdrop-blur py-3">
            <x-ui.button type="submit">{{ __('حفظ') }}</x-ui.button>
            <p class="text-xs text-subtle self-center">
                {{ __('التحويل يدوي: يُرسل العميل رقم العملية، وتعتمدها أنت من شاشة الفاتورة.') }}
            </p>
        </div>
    </form>
</div>
</x-layouts.super-admin>
