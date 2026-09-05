<x-layouts.admin :title="__('حركات الصنف')" current="inventory">
<div class="max-w-[1000px]">

    <x-ui.page-header :title="__('حركات: :item', ['item' => $item->name])"
                      :subtitle="__('الرصيد يتحرّك بالحركات لا بالكتابة — فيبقى معروفاً لماذا نقص ومن أخذ.')"
                      :back="url('/admin/inventory')">
        <x-slot:actions>
            <x-ui.badge :tone="$item->isLow() ? 'danger' : 'neutral'">
                {{ __('الرصيد: :n', ['n' => number_format((int) $item->stock_qty)]) }}
            </x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('status'))<x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>@endif
    @error('qty')<x-ui.alert tone="danger" class="mb-5">{{ $message }}</x-ui.alert>@enderror

    @if($counted !== (int) $item->stock_qty)
        {{--
            اختلاف الرصيد عن مجموع الحركات يعني خللاً لا يُخفى.
            وهو أوّل ما يُسأل عنه حين لا يطابق الجرد الواقع.
        --}}
        <x-ui.alert tone="warning" class="mb-5" :title="__('الرصيد لا يطابق الحركات')">
            {{ __('الرصيد المسجّل :stock، ومجموع الحركات :counted. سجّل تسوية جرد بالفرق.', [
                'stock' => $item->stock_qty,
                'counted' => $counted,
            ]) }}
        </x-ui.alert>
    @endif

    <x-ui.card :title="__('حركة جديدة')" class="mb-6">
        <form method="POST" action="{{ url('/admin/inventory/'.$item->getKey().'/movements') }}"
              class="grid gap-4" x-data="{ type: 'in' }">
            @csrf

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field :label="__('النوع')" for="type" required class="mb-0">
                    <select id="type" name="type" x-model="type"
                            class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                        @foreach(App\Modules\Center\Models\InventoryMovement::TYPES as $key => $meta)
                            @continue($key === 'return')
                            <option value="{{ $key }}">{{ __($meta[0]) }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('الكمية')" for="qty" required class="mb-0">
                    <x-ui.input id="qty" name="qty" type="number" min="1" max="100000" value="1" required />
                </x-ui.field>

                <x-ui.field :label="__('السبب')" for="reason" class="mb-0">
                    <x-ui.input id="reason" name="reason" maxlength="255"
                                :placeholder="__('اختياري — مثل «فاتورة المورّد ٣٤»')" />
                </x-ui.field>
            </div>

            {{-- الطالب والموظّف يظهران حيث يعنيان: البيع لطالب، والعهدة لأيّهما --}}
            <div class="grid gap-4 sm:grid-cols-2" x-show="['sale', 'custody', 'out'].includes(type)" x-cloak>
                <x-ui.field :label="__('الطالب')" for="student_id" class="mb-0">
                    <select id="student_id" name="student_id"
                            class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                        <option value="">{{ __('— لا أحد —') }}</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}">{{ $student->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field :label="__('الموظّف / المدرّس')" for="staff_id" class="mb-0">
                    <select id="staff_id" name="staff_id"
                            class="w-full min-h-11 rounded-md border border-line-strong bg-surface text-sm px-3">
                        <option value="">{{ __('— لا أحد —') }}</option>
                        @foreach($staff as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <div><x-ui.button type="submit">{{ __('سجّل الحركة') }}</x-ui.button></div>
        </form>
    </x-ui.card>

    <x-ui.card :title="__('السجلّ')">
        @if($movements->isEmpty())
            <x-ui.empty :title="__('لا حركات بعد')">
                {{ __('ابدأ بتسجيل التوريد الأول — كم قطعةً دخلت المخزن ومن أين.') }}
            </x-ui.empty>
        @else
            <div class="grid gap-1.5">
                @foreach($movements as $movement)
                    <div class="flex flex-wrap items-center gap-3 py-2 border-b border-line last:border-0">
                        <x-ui.badge :tone="$movement->qty > 0 ? 'success' : 'warning'">
                            {{ $movement->typeLabel() }}
                        </x-ui.badge>

                        <span class="font-mono text-sm tabular {{ $movement->qty > 0 ? 'text-success' : 'text-danger' }}">
                            {{ $movement->qty > 0 ? '+' : '' }}{{ $movement->qty }}
                        </span>

                        <span class="min-w-0 flex-1 text-sm truncate text-muted">
                            {{ $movement->reason ?: '—' }}
                            @if($movement->student_id || $movement->staff_id)
                                · {{ $movement->holder() }}
                            @endif
                        </span>

                        @if($movement->isOpenCustody())
                            <form method="POST" action="{{ url('/admin/inventory/movements/'.$movement->id.'/return') }}">
                                @csrf
                                <x-ui.button size="sm" variant="ghost" type="submit">{{ __('رُدّت') }}</x-ui.button>
                            </form>
                        @elseif($movement->type === 'custody')
                            <span class="text-2xs text-subtle">{{ __('رُدّت :when', ['when' => $movement->returned_at?->diffForHumans()]) }}</span>
                        @endif

                        <span class="text-2xs text-subtle font-mono tabular shrink-0">
                            {{ $movement->created_at?->translatedFormat('j M · H:i') }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $movements->links() }}</div>
        @endif
    </x-ui.card>

</div>
</x-layouts.admin>
