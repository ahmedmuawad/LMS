<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Entitlements\Entitlements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ADR-014 — تسعير مثبّت لكل عملة (بأصغر وحدة)، لا تحويل بسعر الصرف.
 * الأرقام هنا مبدئية وتُضبط من لوحة الإدارة بعد دراسة السوق.
 */
final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            'starter' => [
                'name' => ['ar' => 'البداية', 'en' => 'Starter'],
                'tagline' => ['ar' => 'لمن يبدأ كورسه الأول', 'en' => 'For your first course'],
                'prices' => ['EGP' => 49900, 'SAR' => 7900, 'AED' => 7900, 'USD' => 1900],

                /*
                 | «البداية» تسمح بنمط المجموعات أيضاً.
                 |
                 | مدرّس الحصص هو سوقنا الأول، والمجموعات والحضور عنده
                 | ليست ميزة متقدّمة بل هي المنتج. حجبها عن أرخص باقة
                 | يجعلها باقة لا يشتريها أحد ممن نستهدفهم — والحدّ
                 | الحقيقي هنا كمّي لا نوعي: مئة طالب ومدرّس واحد.
                 */
                'modes' => ['solo', 'teacher'],
                'trial' => 14,
                'features' => [
                    'students' => '100', 'instructors' => '1', 'staff' => '1', 'courses' => '10',

                    /*
                     | حدّ المجموعات مذكورٌ صراحةً.
                     |
                     | الباقة تسمح بنمط «مجموعات وحصص وطلبة»، وحدٌّ غائب
                     | يُقرأ صفراً لا «بلا حدّ» — فيشتري المدرّس نمطاً
                     | لا يستطيع إنشاء مجموعة واحدة فيه. وخمس عشرة
                     | مجموعة تناسب مئة طالب.
                     */
                    'groups' => '15',

                    'storage_gb' => '5', 'video_minutes' => '5000', 'emails' => '2000',
                    'blog' => '1', 'live_zoom' => '1', 'live_jitsi' => '1',
                    'video_watermark' => '1', 'screenshot_block' => '1',
                    'device_limit' => '2', 'center_management' => '1', 'parent_portal' => '1',
                    'center_finance' => '1', 'gamification' => '1', 'page_builder' => '1',
                ],
            ],
            'growth' => [
                'name' => ['ar' => 'النمو', 'en' => 'Growth'],
                'tagline' => ['ar' => 'الأكثر طلباً', 'en' => 'Most popular'],
                'prices' => ['EGP' => 149900, 'SAR' => 24900, 'AED' => 24900, 'USD' => 5900],
                'modes' => ['solo', 'teacher', 'marketplace'],
                'trial' => 14,
                'features' => [
                    'students' => '1000', 'instructors' => '10', 'staff' => '5', 'courses' => 'unlimited',
                    'storage_gb' => '50', 'video_minutes' => '50000', 'emails' => '20000',
                    'sms' => '500', 'whatsapp' => '2000', 'groups' => '50',
                    'center_management' => '1', 'center_finance' => '1', 'parent_portal' => '1',
                    'custom_domain' => '1', 'white_label' => '1', 'multi_language' => '1',
                    'multi_instructor' => '1', 'page_builder' => '1', 'blog' => '1',
                    'services_module' => '1', 'subscriptions' => '1', 'installments' => '1',
                    'recharge_codes' => '1', 'coaching' => '1', 'gamification' => '1',
                    'video_watermark' => '1', 'screenshot_block' => '1', 'device_limit' => '2',
                    'offline_download' => '1', 'live_zoom' => '1', 'live_meet' => '1',
                    'live_bbb' => '1', 'live_jitsi' => '1', 'email_automation' => '1',
                    'whatsapp_api' => '1', 'affiliates' => '1', 'community' => '1',
                    'api_access' => '1', 'ai_assistant' => '1',
                ],
            ],
            'professional' => [
                'name' => ['ar' => 'الاحترافية', 'en' => 'Professional'],
                'tagline' => ['ar' => 'لمن يبني مؤسسة', 'en' => 'For institutions'],
                'prices' => ['EGP' => 299900, 'SAR' => 49900, 'AED' => 49900, 'USD' => 11900],
                'modes' => ['solo', 'teacher', 'marketplace', 'center', 'hybrid'],
                'trial' => 14,
                'features' => [
                    'students' => 'unlimited', 'instructors' => 'unlimited', 'staff' => 'unlimited',
                    'courses' => 'unlimited', 'storage_gb' => '500', 'video_minutes' => 'unlimited',
                    'emails' => 'unlimited', 'sms' => '5000', 'whatsapp' => 'unlimited',
                    'branches' => '5', 'groups' => 'unlimited',
                    'community' => '1', 'services_module' => '1',
                    'custom_domain' => '1', 'white_label' => '1', 'custom_css' => '1',
                    'multi_language' => '1', 'multi_currency' => '1', 'api_access' => '1',
                    'mobile_app' => '1', 'multi_instructor' => '1',
                    'interactive_video' => '1', 'h5p' => '1', 'scorm' => '1', 'xapi' => '1',
                    'adaptive_learning' => '1', 'proctoring' => '1', 'gamification' => '1',
                    'offline_download' => '1', 'drm' => '1', 'screenshot_block' => '1',
                    'device_limit' => '3', 'video_watermark' => '1',
                    'live_zoom' => '1', 'live_meet' => '1', 'live_bbb' => '1', 'live_jitsi' => '1',
                    'center_management' => '1', 'attendance_devices' => '1', 'parent_portal' => '1',
                    'center_finance' => '1', 'inventory' => '1',
                    // «السنتر» تبيع النمط الشامل، وفيه المجتمع والخدمات
                    'community' => '1', 'services_module' => '1',
                    'services_module' => '1', 'physical_products' => '1', 'subscriptions' => '1',
                    'installments' => '1', 'recharge_codes' => '1', 'coaching' => '1',
                    'funnels' => '1', 'email_automation' => '1', 'whatsapp_api' => '1',
                    'affiliates' => '1', 'page_builder' => '1', 'blog' => '1', 'community' => '1',
                    'ai_assistant' => '1', 'ai_course_builder' => '1', 'ai_exam_from_pdf' => '1',
                    'priority_support' => '1',
                ],
            ],
            'center' => [
                'name' => ['ar' => 'السنتر', 'en' => 'Learning Center'],
                'tagline' => ['ar' => 'للسناتر التعليمية الأرضية', 'en' => 'For physical learning centers'],
                'prices' => ['EGP' => 199900, 'SAR' => 34900, 'AED' => 34900, 'USD' => 8900],
                'modes' => ['teacher', 'center', 'hybrid'],
                'trial' => 14,
                'features' => [
                    'students' => '2000', 'instructors' => '50', 'staff' => '20', 'courses' => 'unlimited',
                    // «السنتر» تبيع النمط الشامل، وفيه المجتمع والخدمات
                    'community' => '1', 'services_module' => '1',
                    'storage_gb' => '100', 'video_minutes' => '100000', 'emails' => '50000',
                    'sms' => '10000', 'whatsapp' => '20000', 'branches' => '3', 'groups' => 'unlimited',
                    'center_management' => '1', 'attendance_devices' => '1', 'parent_portal' => '1',
                    'center_finance' => '1', 'inventory' => '1',
                    'custom_domain' => '1', 'white_label' => '1', 'multi_instructor' => '1',
                    'page_builder' => '1', 'blog' => '1', 'gamification' => '1',
                    'video_watermark' => '1', 'screenshot_block' => '1', 'device_limit' => '2',
                    'live_zoom' => '1', 'live_meet' => '1', 'live_bbb' => '1', 'live_jitsi' => '1',
                    'recharge_codes' => '1', 'installments' => '1',
                    'whatsapp_api' => '1', 'email_automation' => '1', 'ai_assistant' => '1',
                ],
            ],
        ];

        $position = 0;

        foreach ($plans as $key => $plan) {
            DB::table('plans')->updateOrInsert(['key' => $key], [
                'name' => json_encode($plan['name'], JSON_UNESCAPED_UNICODE),
                'tagline' => json_encode($plan['tagline'], JSON_UNESCAPED_UNICODE),
                'prices' => json_encode($plan['prices']),
                'interval' => 'month',
                'interval_count' => 1,
                'trial_days' => $plan['trial'],
                'modes' => json_encode($plan['modes']),
                'is_public' => true,
                'is_active' => true,
                'position' => $position++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($plan['features'] as $feature => $value) {
                DB::table('plan_features')->updateOrInsert(
                    ['plan_key' => $key, 'feature_key' => $feature],
                    ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
                );
            }

            /*
             | ما لم يعد في التعريف يُحذف.
             |
             | `updateOrInsert` وحده يُبقي صفوفاً من نسخةٍ سابقة إلى
             | الأبد: ميزةٌ حُذفت من الباقة تبقى ممنوحةً في القاعدة،
             | فتُعرض في صفحة الأسعار وتُباع وهي غير مقصودة.
             */
            DB::table('plan_features')
                ->where('plan_key', $key)
                ->whereNotIn('feature_key', array_keys($plan['features']))
                ->delete();
        }

        /*
         | إبطال الاستحقاقات المخزَّنة.
         |
         | بلا هذا يبقى كل مشترك ساعةً على حدوده القديمة — والحدّ
         | المضاف حديثاً يُقرأ غائباً، وغيابُه يعني صفراً لا «بلا
         | حدّ»، فيُمنع المشترك ممّا اشتراه للتوّ.
         */
        Entitlements::bumpVersion();
    }
}
