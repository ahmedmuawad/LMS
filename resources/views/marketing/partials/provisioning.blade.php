<x-marketing.section
    tone="tint"
    :eyebrow="__('التجهيز الآلي')"
    :title="__('من الاشتراك إلى منصّة عاملة في أقل من دقيقة')"
    :lead="__('لا انتظار ولا فريق تجهيز ولا مكالمة. وكل خطوة قابلة للتراجع عند الفشل ومسجّلة في سجل التجهيز.')"
>
    <div class="relative">
        {{-- الخيط الواصل: يظهر على الشاشات الواسعة وحدها حيث تصطف الخطوات --}}
        <div class="hidden lg:block absolute inset-x-12 top-6 h-px bg-line" aria-hidden="true"></div>

        <ol class="relative grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-6">
            @foreach(config('marketing.provisioning', []) as $step)
                <li class="min-w-0">
                    <span class="relative inline-grid place-items-center size-12 rounded-full text-primary-on font-display font-extrabold text-lg mb-5 shadow-md"
                          style="background-image: linear-gradient(140deg, var(--color-primary), var(--sem-primary-hover));"
                          aria-hidden="true">{{ $step['step'] }}</span>
                    <h3 class="font-bold text-lg mb-2 leading-snug">{{ __($step['title']) }}</h3>
                    <p class="text-muted leading-relaxed">{{ __($step['body']) }}</p>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-px bg-line rounded-2xl overflow-hidden border border-line mt-12">
        @foreach([
            ['t' => 'أداؤك ليس مسؤوليتك', 'b' => 'LCP ≤ 2 ثانية على الموبايل، وTTFB ≤ 200 ملّي ثانية للصفحة المؤرشفة — ميزانية أداء تُقاس في CI وتمنع النشر عند تجاوزها.'],
            ['t' => 'وسيوك ليس إعداداً ثانوياً', 'b' => 'Schema وsitemap وrobots وhreflang وقواعد 301 — مستقلة لكل نطاق، ومربوطة بـ Search Console من داخل لوحتك.'],
            ['t' => 'وتحديثاتك بلا مفاجآت', 'b' => 'إصدار واحد للجميع بنشر تدريجي (5٪ ثم 25٪ ثم الكل)، ونافذة صيانة لكل مجموعة خوادم على حدة، وسجل تغييرات داخل لوحتك بلغتك.'],
        ] as $item)
            <div class="bg-surface p-6 min-w-0">
                <h3 class="font-bold text-lg mb-2">{{ __($item['t']) }}</h3>
                <p class="text-muted leading-relaxed">{{ __($item['b']) }}</p>
            </div>
        @endforeach
    </div>
</x-marketing.section>
