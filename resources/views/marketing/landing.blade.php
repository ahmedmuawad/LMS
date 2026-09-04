{{--
    صفحتنا نحن — الطبقة الأولى في وثيقة 14: نبيع اشتراك المنصّة
    للمشترك، لا كورساً لطالب. لا تقرأ إعدادات مشترك ولا تفترض
    وجود سياق مستأجر.
--}}
<x-layouts.marketing :title="__('منصّة الكورسات والخدمات والسناتر — أونلاين وحضورياً في نظام واحد')">

    @include('marketing.partials.hero')
    @include('marketing.partials.marquee')
    @include('marketing.partials.modes')
    @include('marketing.partials.advantages')
    @include('marketing.partials.features')
    @include('marketing.partials.center')
    @include('marketing.partials.compare')
    @include('marketing.partials.payments')
    @include('marketing.partials.provisioning')
    @include('marketing.partials.pricing')
    @include('marketing.partials.faq')
    @include('marketing.partials.cta')

</x-layouts.marketing>
