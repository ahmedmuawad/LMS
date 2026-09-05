<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ADR-011 — سجلّ المزايا. كل ميزة مبنية في النظام؛ الباقة تقرّر إتاحتها.
 */
final class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // [key, ar, en, type, unit, group, resets]
            // ---------- الحدود ----------
            ['students',        'عدد الطلاب',            'Students',          'limit', 'طالب',   'limits', null],
            ['instructors',     'عدد المدرّسين',          'Instructors',       'limit', 'مدرّس',  'limits', null],
            ['staff',           'عدد الموظفين',          'Staff members',     'limit', 'موظف',   'limits', null],
            ['courses',         'عدد الكورسات',          'Courses',           'limit', 'كورس',   'limits', null],
            ['storage_gb',      'مساحة التخزين',         'Storage',           'limit', 'جيجابايت', 'limits', null],
            ['branches',        'عدد الفروع',            'Branches',          'limit', 'فرع',    'limits', null],
            ['groups',          'عدد المجموعات',         'Groups',            'limit', 'مجموعة', 'limits', null],
            // ---------- الحصص المتجدّدة شهرياً ----------
            ['ai_requests',     'طلبات الذكاء الاصطناعي', 'AI requests',      'quota', 'طلب',    'quotas', 'month'],
            ['video_minutes',   'دقائق مشاهدة الفيديو',  'Video minutes',     'quota', 'دقيقة',  'quotas', 'month'],
            ['live_hours',      'ساعات البث المباشر',    'Live hours',        'quota', 'ساعة',   'quotas', 'month'],
            ['emails',          'رسائل البريد',          'Emails',            'quota', 'رسالة',  'quotas', 'month'],
            ['sms',             'رسائل SMS',             'SMS messages',      'quota', 'رسالة',  'quotas', 'month'],
            ['whatsapp',        'رسائل واتساب',          'WhatsApp messages', 'quota', 'رسالة',  'quotas', 'month'],
            // ---------- المنصة ----------
            ['custom_domain',   'نطاق خاص',              'Custom domain',     'boolean', null, 'platform', null],
            ['white_label',     'إزالة العلامة التجارية', 'White label',       'boolean', null, 'platform', null],
            ['custom_css',      'CSS مخصّص',             'Custom CSS',        'boolean', null, 'platform', null],
            ['multi_language',  'تعدّد اللغات',           'Multi-language',    'boolean', null, 'platform', null],
            ['multi_currency',  'تعدّد العملات',          'Multi-currency',    'boolean', null, 'platform', null],
            ['api_access',      'الوصول للـ API',        'API access',        'boolean', null, 'platform', null],
            ['mobile_app',      'تطبيق موبايل باسمك',    'Branded mobile app', 'boolean', null, 'platform', null],
            ['data_residency',  'إقامة البيانات إقليمياً', 'Data residency',    'boolean', null, 'platform', null],
            // ---------- التعليم ----------
            ['multi_instructor', 'تعدّد المدرّسين',        'Multiple instructors', 'boolean', null, 'lms', null],
            ['interactive_video', 'الفيديو التفاعلي',      'Interactive video', 'boolean', null, 'lms', null],
            ['h5p',             'محتوى H5P',             'H5P content',       'boolean', null, 'lms', null],
            ['scorm',           'استيراد SCORM',         'SCORM import',      'boolean', null, 'lms', null],
            ['xapi',            'تتبّع xAPI',            'xAPI tracking',     'boolean', null, 'lms', null],
            ['adaptive_learning', 'التعلّم التكيّفي',       'Adaptive learning', 'boolean', null, 'lms', null],
            ['proctoring',      'مراقبة الامتحان',       'Proctoring',        'boolean', null, 'lms', null],
            ['gamification',    'التلعيب والنقاط',       'Gamification',      'boolean', null, 'lms', null],
            ['offline_download', 'التحميل دون اتصال',     'Offline download',  'boolean', null, 'lms', null],
            // ---------- الحماية ----------
            ['drm',             'حماية DRM',             'DRM protection',    'boolean', null, 'protection', null],
            ['screenshot_block', 'منع لقطة الشاشة',       'Screenshot block',  'boolean', null, 'protection', null],
            ['device_limit',    'تقييد عدد الأجهزة',     'Device limit',      'boolean', null, 'protection', null],
            ['video_watermark', 'علامة مائية على الفيديو', 'Video watermark',   'boolean', null, 'protection', null],
            // ---------- اللايف ----------
            ['live_zoom',       'تكامل Zoom',            'Zoom integration',  'boolean', null, 'live', null],
            ['live_meet',       'تكامل Google Meet',     'Meet integration',  'boolean', null, 'live', null],
            ['live_bbb',        'تكامل BigBlueButton',   'BBB integration',   'boolean', null, 'live', null],
            ['live_jitsi',      'تكامل Jitsi',           'Jitsi integration', 'boolean', null, 'live', null],
            // ---------- السنتر ----------
            ['center_management', 'إدارة السنتر',          'Center management', 'boolean', null, 'center', null],
            ['attendance_devices', 'أجهزة الحضور (QR/بصمة)', 'Attendance devices', 'boolean', null, 'center', null],
            ['parent_portal',   'بوابة أولياء الأمور',   'Parent portal',     'boolean', null, 'center', null],
            ['center_finance',  'أقساط وخزنة ومصروفات',  'Center finance',    'boolean', null, 'center', null],
            ['inventory',       'المخزون والعُهد',        'Inventory',         'boolean', null, 'center', null],
            // ---------- التجارة ----------
            ['services_module', 'بيع الخدمات',           'Services module',   'boolean', null, 'commerce', null],
            ['physical_products', 'المنتجات المادية',      'Physical products', 'boolean', null, 'commerce', null],
            ['subscriptions',   'الاشتراكات المتكررة',   'Subscriptions',     'boolean', null, 'commerce', null],
            ['installments',    'التقسيط',               'Installments',      'boolean', null, 'commerce', null],
            ['recharge_codes',  'أكواد الشحن',           'Recharge codes',    'boolean', null, 'commerce', null],
            ['coaching',        'الجلسات الفردية',       'Coaching sessions', 'boolean', null, 'commerce', null],
            // ---------- التسويق ----------
            ['funnels',         'القمع التسويقي',        'Sales funnels',     'boolean', null, 'marketing', null],
            ['email_automation', 'أتمتة البريد',          'Email automation',  'boolean', null, 'marketing', null],
            ['whatsapp_api',    'واتساب للأعمال',        'WhatsApp API',      'boolean', null, 'marketing', null],
            ['affiliates',      'التسويق بالعمولة',      'Affiliate program', 'boolean', null, 'marketing', null],
            ['page_builder',    'محرّر الصفحات',         'Page builder',      'boolean', null, 'marketing', null],
            ['blog',            'المدونة',               'Blog',              'boolean', null, 'marketing', null],
            ['community',       'المجتمع والنقاشات',     'Community',         'boolean', null, 'marketing', null],
            // ---------- الذكاء الاصطناعي والدعم ----------
            ['ai_assistant',    'المساعد الذكي',         'AI assistant',      'boolean', null, 'ai', null],
            ['ai_course_builder', 'بناء كورس بالذكاء الاصطناعي', 'AI course builder', 'boolean', null, 'ai', null],
            ['ai_exam_from_pdf', 'توليد امتحان من PDF',   'AI exam from PDF',  'boolean', null, 'ai', null],
            ['priority_support', 'دعم فني بأولوية',       'Priority support',  'boolean', null, 'support', null],
        ];

        foreach ($rows as $i => [$key, $ar, $en, $type, $unit, $group, $resets]) {
            DB::table('features')->updateOrInsert(['key' => $key], [
                'name' => json_encode(['ar' => $ar, 'en' => $en], JSON_UNESCAPED_UNICODE),
                'type' => $type,
                'unit' => $unit,
                'group' => $group,
                'resets' => $resets,
                'is_visible' => true,
                'position' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
