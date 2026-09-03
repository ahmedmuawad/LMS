<x-layouts.admin :title="__('توليد أكواد الشحن')" current="recharge-codes">
<div class="max-w-[1000px]">

    <x-ui.page-header :title="__('أكواد الشحن')"
                      :subtitle="__('كروت تُطبع وتُباع في المكتبات — أهم وسيلة دفع لمن لا يملك بطاقة.')">
        <x-slot:actions>
            <x-ui.button size="sm" variant="secondary" :href="url('/admin/recharge-codes')">{{ __('كل الأكواد') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))<x-ui.alert tone="success" class="mb-4">{{ session('status') }}</x-ui.alert>@endif

    <div class="grid gap-4 lg:grid-cols-[340px_minmax(0,1fr)] items-start">

        <x-ui.card :title="__('دفعة جديدة')">
            <form method="POST" action="{{ url('/admin/recharge-codes/generate') }}"
                  x-data="{ type: @js(old('type', 'wallet')) }">
                @csrf

                <x-ui.field :label="__('اسم الدفعة')" for="name" :required="true" :error="$errors->first('name')"
                            :hint="__('لتعرفها لاحقاً: «كروت سبتمبر — مكتبة النور».')">
                    <x-ui.input name="name" id="name" value="{{ old('name') }}" />
                </x-ui.field>

                <x-ui.field :label="__('العدد')" for="quantity" :required="true" :error="$errors->first('quantity')">
                    <x-ui.input name="quantity" id="quantity" type="number" min="1" max="10000"
                                value="{{ old('quantity', 100) }}" class="font-mono" />
                </x-ui.field>

                <x-ui.field :label="__('النوع')" for="type">
                    <x-ui.select name="type" id="type" x-model="type">
                        <option value="wallet">{{ __('شحن رصيد') }}</option>
                        <option value="course">{{ __('فتح كورس') }}</option>
                    </x-ui.select>
                </x-ui.field>

                <div x-show="type === 'wallet'">
                    <x-ui.field :label="__('قيمة الكود')" for="value" :error="$errors->first('value')">
                        <x-ui.input name="value" id="value" type="number" step="0.01" min="0"
                                    value="{{ old('value', 100) }}" class="font-mono" />
                    </x-ui.field>
                </div>

                <div x-show="type !== 'wallet'" x-cloak>
                    <x-ui.field :label="__('الكورس')" for="course_id" :error="$errors->first('course_id')">
                        <x-ui.select name="course_id" id="course_id">
                            <option value="">{{ __('اختر كورساً…') }}</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->title }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <x-ui.field :label="__('تنتهي في')" for="expires_at" :error="$errors->first('expires_at')"
                            :hint="__('اتركه فارغاً لبلا انتهاء.')">
                    <x-ui.input name="expires_at" id="expires_at" type="date" value="{{ old('expires_at') }}" />
                </x-ui.field>

                <x-ui.button type="submit" class="w-full">{{ __('توليد') }}</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card :title="__('الدفعات')" :padding="false">
            @if($batches->isEmpty())
                <div class="p-5">
                    <x-ui.empty :title="__('لا دفعات بعد')">{{ __('ولّد دفعتك الأولى من اليسار، ثم صدّرها للطباعة.') }}</x-ui.empty>
                </div>
            @else
                <div class="overflow-x-auto">
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ([__('الدفعة'), __('النوع'), __('العدد'), __('المستخدَم'), __('القيمة'), ''] as $th)
                                    <th class="bg-surface-sunken text-start font-semibold text-xs text-muted px-4 py-3 border-b border-line whitespace-nowrap">{{ $th }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($batches as $batch)
                                <tr class="hover:bg-surface-sunken transition-colors">
                                    <td class="px-4 py-3 border-b border-line text-sm">
                                        {{ $batch->name }}
                                        <span class="block text-2xs text-subtle">{{ $batch->created_at?->format('Y-m-d') }}</span>
                                    </td>
                                    <td class="px-4 py-3 border-b border-line text-sm">
                                        {{ __(App\Modules\Commerce\Models\RechargeCode::TYPES[$batch->type] ?? $batch->type) }}
                                    </td>
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular">{{ number_format($batch->codes_count) }}</td>
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular">{{ number_format($batch->usedCount()) }}</td>
                                    <td class="px-4 py-3 border-b border-line font-mono text-xs tabular">
                                        {{ $batch->type === 'wallet' ? $batch->value()->format() : '—' }}
                                    </td>
                                    <td class="px-4 py-3 border-b border-line">
                                        <x-ui.button size="sm" variant="ghost" :href="url('/admin/recharge-codes/batches/'.$batch->id.'/export')">
                                            {{ __('تصدير') }}
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
</x-layouts.admin>
