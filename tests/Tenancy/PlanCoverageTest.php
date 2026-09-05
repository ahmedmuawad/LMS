<?php

declare(strict_types=1);

use App\Core\Entitlements\Models\Plan;
use App\Core\Entitlements\Models\PlanFeature;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

/*
 | الباقة تمنح ما تحتاجه أنماطها.
 |
 | نمطٌ يُباع ولا تُمنح أدواته وعدٌ لا يُنفَّذ: اشترى مدرّس «مجموعات
 | وحصص وطلبة» في باقة «البداية»، فوجد حدّ المجموعات غائباً — وغياب
 | الحدّ يعني صفراً لا «بلا حدّ»، فلم يستطع إنشاء مجموعة واحدة.
 |
 | هذا الاختبار يمشي على كل باقة، وكل نمط تسمح به، وكل موديول
 | يفعّله ذلك النمط — ويفشل على أول أداةٍ غير ممنوحة.
 */

beforeEach(function () {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

/** ما يحتاجه كل موديول من حدود أو مزايا ليعمل فعلاً */
$requirements = [
    'lms' => ['courses', 'students'],
    'center' => ['groups', 'center_management'],
    'center-premises' => ['branches'],
    'center-staff' => ['staff'],
    'center-finance' => ['center_finance'],
    'parent-portal' => ['parent_portal'],
    'instructors' => ['instructors', 'multi_instructor'],
    'services' => ['services_module'],
    'blog' => ['blog'],
    'gamification' => ['gamification'],
    'community' => ['community'],
    'page-builder' => ['page_builder'],
    'inventory' => ['inventory'],
];

it('grants every plan what the modes it sells actually need', function () use ($requirements) {
    $missing = [];

    foreach (Plan::with('features')->get() as $plan) {
        $granted = $plan->features->pluck('value', 'feature_key');

        foreach ((array) $plan->modes as $mode) {
            $modules = config("platform-modes.modes.{$mode}.modules", []);

            foreach ($modules as $module) {
                foreach ($requirements[$module] ?? [] as $needed) {
                    if (! $granted->has($needed)) {
                        $missing[] = "[{$plan->key}] {$mode} → {$module} يحتاج {$needed}";
                    }
                }
            }
        }
    }

    expect($missing)->toBe([], implode("\n", array_unique($missing)));
});

it('never grants a numeric limit of zero, which reads as forbidden', function () {
    /*
     | صفرٌ في القاعدة يساوي المنع تماماً.
     |
     | فمن أراد المنع حذف المفتاح، ومن أراد حدّاً كتب رقماً. وصفرٌ
     | مكتوبٌ عمداً لا معنى له إلا إرباك من يقرأ صفحة الأسعار.
     */
    $zeros = PlanFeature::where('value', '0')->get()
        ->map(fn (PlanFeature $f): string => "{$f->plan_key}.{$f->feature_key}");

    expect($zeros->all())->toBe([]);
});

it('keeps the public plan page and the enforcement layer in agreement', function () {
    /*
     | ما تعرضه صفحة الأسعار هو ما تُنفّذه الطبقة.
     |
     | كانت الصفحة تقرأ القاعدة والطبقة تقرأ كاشاً عمره ساعة، فيرى
     | المشترك «١٥ مجموعة» ويُمنع عند الأولى.
     */
    $tenant = makeTenant('starter');

    foreach (PlanFeature::where('plan_key', 'starter')->get() as $feature) {
        $shown = $feature->value;

        $enforced = $shown === 'unlimited'
            ? ($tenant->limitOf($feature->feature_key) === null ? 'unlimited' : 'mismatch')
            : (string) ($tenant->limitOf($feature->feature_key) ?? 'null');

        expect($enforced)->toBe($shown, "الميزة {$feature->feature_key}: الصفحة تقول {$shown}");
    }
});
