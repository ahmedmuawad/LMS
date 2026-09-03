<x-layouts.app :title="__('أبنائي')">
<x-site.header />

<main id="main" class="max-w-[900px] mx-auto px-4 sm:px-6 py-8">

    <x-ui.page-header :title="__('أبنائي')"
                      :subtitle="__('حضورهم ودرجاتهم ومستحقاتهم — بلا حاجة إلى مكالمة.')" />

    @if($children->isEmpty())
        <x-ui.card>
            <x-ui.empty :title="__('لا أبناء مرتبطون بحسابك')">
                {{ __('تواصل مع إدارة السنتر لربط أبنائك بحسابك.') }}
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($children as $child)
                <x-ui.card>
                    <div class="flex items-center gap-3 mb-3">
                        <x-ui.avatar :name="$child->name()" />
                        <div class="min-w-0">
                            <p class="font-semibold truncate">{{ $child->name() }}</p>
                            <p class="text-2xs text-subtle font-mono">{{ $child->code }} · {{ $child->grade?->name }}</p>
                        </div>
                    </div>

                    <x-ui.button size="sm" variant="secondary" class="w-full"
                                 :href="url('/guardian/children/'.$child->id)">
                        {{ __('عرض التفاصيل') }}
                    </x-ui.button>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</main>

<x-site.footer />
</x-layouts.app>
