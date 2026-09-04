@php
    /*
     | كل اسم هنا مدروس في وثيقة 07 وله بطاقة تفصيلية فيها.
     | الشريط يمرّ مرّتين: النسخة الثانية هي ما يجعل الدوران متصلاً.
     */
    $names = [
        'Kajabi', 'LearnWorlds', 'Thinkific', 'Teachable', 'Podia', 'Klasio', 'Graphy',
        'زمن', 'سيلينك', 'Prime E', 'EdSentre', 'اختباراتي', 'المنصة',
        'سمارت سنتر', 'G‑Students', 'PlanKit', 'Skolera', 'Classera',
        'Skool', 'Circle', 'Mighty Networks',
        'TalentLMS', 'Docebo', 'Moodle', 'Canvas',
        'LearnDash', 'TutorLMS', 'LifterLMS', 'MasterStudy', 'WPLMS',
    ];
@endphp

<div class="border-y border-line bg-surface-sunken py-6 overflow-hidden">
    <p class="text-center text-2xs font-semibold tracking-wide text-subtle mb-4 px-4">
        {{ __('درسنا 30 منافساً في 7 فئات — وكل ميزة وجدناها عند أحدهم دخلت نطاقنا') }}
    </p>

    {{-- التلاشي على الحافّتين بقناع متماثل: لا يعرف يميناً من يسار، فيصحّ في الاتجاهين --}}
    <div aria-hidden="true"
         style="mask-image: linear-gradient(to right, transparent, #000 4rem, #000 calc(100% - 4rem), transparent);
                -webkit-mask-image: linear-gradient(to right, transparent, #000 4rem, #000 calc(100% - 4rem), transparent);">
        <div class="marquee-track gap-3">
            @foreach([1, 2] as $pass)
                @foreach($names as $name)
                    <span class="shrink-0 whitespace-nowrap rounded-full border border-line bg-surface px-4 py-1.5 text-sm font-semibold text-muted">{{ $name }}</span>
                @endforeach
            @endforeach
        </div>
    </div>
</div>
