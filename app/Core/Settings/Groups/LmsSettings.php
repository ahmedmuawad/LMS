<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Settings\SettingsGroup;

final class LmsSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'lms';
    }

    public function label(): string
    {
        return __('التعليم');
    }

    public function icon(): string
    {
        return '🎓';
    }

    public function module(): ?string
    {
        return 'lms';
    }

    public function description(): ?string
    {
        return __('سلوك الكورسات والاختبارات والواجبات والشهادات.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('الكورسات'))->fields([
                SelectField::make('default_status')->label(__('الحالة الافتراضية للكورس الجديد'))->half()
                    ->options(['draft' => __('مسودّة'), 'pending' => __('بانتظار الاعتماد'), 'published' => __('منشور')])
                    ->default('draft'),
                SwitchField::make('require_approval')->label(__('اعتماد الإدارة قبل النشر'))->default(true),
                NumberField::make('default_access_days')->label(__('مدة الوصول الافتراضية'))->suffix(__('يوم'))
                    ->range(0, 3650)->half()->default(365)->hint(__('صفر يعني وصولاً دائماً.')),
                SelectField::make('on_expiry')->label(__('عند انتهاء مدة الوصول'))->half()
                    ->options(['block' => __('منع الدخول'), 'read_only' => __('قراءة فقط بلا اختبارات')])
                    ->default('read_only'),
                SwitchField::make('sequential')->label(__('التسلسل الإجباري افتراضياً'))->default(false),
                SelectField::make('drip')->label(__('استراتيجية الفتح التدريجي'))->half()
                    ->options([
                        'off' => __('بلا فتح تدريجي'),
                        'by_days' => __('بعد أيام من التسجيل'),
                        'by_date' => __('في تواريخ محدّدة'),
                        'by_completion' => __('بعد إكمال ما قبله'),
                    ])->default('off'),
                NumberField::make('free_preview_lessons')->label(__('دروس مجانية للمعاينة'))->range(0, 20)->half()->default(1),
            ]),

            Section::make(__('مشغّل الفيديو'))->fields([
                SwitchField::make('allow_download')->label(__('السماح بتنزيل الدروس'))->default(false),
                NumberField::make('concurrent_streams')->label(__('حد المشاهدة المتزامنة لكل حساب'))
                    ->range(0, 10)->half()->default(2)->hint(__('صفر يعني بلا حد — يفتح الباب لمشاركة الحسابات.')),
                SwitchField::make('resume')->label(__('الاستئناف من آخر نقطة'))->default(true),
                SwitchField::make('playback_speed')->label(__('التحكم في سرعة التشغيل'))->default(true),
                SwitchField::make('captions')->label(__('الترجمات المصاحبة'))->default(true),
                SwitchField::make('keyboard_shortcuts')->label(__('اختصارات لوحة المفاتيح'))->default(true),
                SwitchField::make('notes')->label(__('ملاحظات الطالب على الدرس'))->default(true),
                SwitchField::make('lesson_questions')->label(__('الأسئلة على الدرس'))->default(true),
            ]),

            Section::make(__('الاختبارات'))->fields([
                NumberField::make('quiz_minutes')->label(__('الزمن الافتراضي'))->suffix(__('دقيقة'))->range(0, 600)->half()->default(30),
                NumberField::make('quiz_attempts')->label(__('عدد المحاولات'))->range(0, 20)->half()->default(3)
                    ->hint(__('صفر يعني محاولات غير محدودة.')),
                NumberField::make('passing_percentage')->label(__('نسبة النجاح'))->suffix('%')->range(0, 100)->half()->default(60),
                SwitchField::make('shuffle_questions')->label(__('خلط ترتيب الأسئلة'))->default(true),
                SwitchField::make('shuffle_answers')->label(__('خلط ترتيب الإجابات'))->default(true),
                SelectField::make('show_answers')->label(__('إظهار الإجابات الصحيحة'))->half()
                    ->options([
                        'never' => __('أبداً'),
                        'after_submit' => __('بعد التسليم'),
                        'after_pass' => __('بعد النجاح فقط'),
                    ])->default('after_pass'),
                SwitchField::make('negative_marking')->label(__('الدرجات السالبة للخطأ'))->default(false),
                NumberField::make('cooldown_minutes')->label(__('فترة انتظار بين المحاولات'))->suffix(__('دقيقة'))
                    ->range(0, 10080)->half()->default(0),
                SwitchField::make('block_copy')->label(__('منع النسخ داخل الاختبار'))->default(true),
                SwitchField::make('detect_blur')->label(__('كشف مغادرة صفحة الاختبار'))->default(true),
                NumberField::make('autosave_seconds')->label(__('حفظ تلقائي كل'))->suffix(__('ثانية'))->range(5, 300)->half()->default(20),
                SwitchField::make('auto_submit')->label(__('تسليم تلقائي عند انتهاء الوقت'))->default(true),
            ]),

            Section::make(__('الواجبات'))->fields([
                NumberField::make('assignment_max_mb')->label(__('أقصى حجم ملف'))->suffix('MB')->range(1, 512)->half()->default(25),
                TextField::make('assignment_extensions')->label(__('الامتدادات المسموحة'))
                    ->default('pdf,doc,docx,jpg,png,zip')->hint(__('مفصولة بفواصل.')),
                SwitchField::make('allow_late')->label(__('السماح بالتسليم المتأخر'))->default(true),
                NumberField::make('late_penalty')->label(__('خصم التأخير'))->suffix('%')->range(0, 100)->half()->default(10),
                NumberField::make('resubmissions')->label(__('مرات إعادة التسليم'))->range(0, 10)->half()->default(1),
                NumberField::make('reminder_hours')->label(__('التذكير قبل الموعد'))->suffix(__('ساعة'))->range(0, 168)->half()->default(24),
            ]),

            Section::make(__('الشهادات'))->fields([
                SwitchField::make('auto_certificate')->label(__('إصدار تلقائي عند الإكمال'))->default(true),
                NumberField::make('certificate_threshold')->label(__('نسبة الإكمال المطلوبة'))->suffix('%')->range(1, 100)->half()->default(100),
                TextField::make('certificate_code_format')->label(__('صيغة كود الشهادة'))->half()
                    ->default('CERT-{YEAR}-{SEQ}')->hint(__('المتغيّرات: {YEAR} · {SEQ} · {COURSE}')),
                NumberField::make('certificate_valid_months')->label(__('صلاحية الشهادة'))->suffix(__('شهر'))
                    ->range(0, 600)->half()->default(0)->hint(__('صفر يعني بلا انتهاء.')),
                SwitchField::make('public_verification')->label(__('صفحة تحقّق عامة'))->default(true),
                SwitchField::make('certificate_qr')->label(__('رمز QR على الشهادة'))->default(true),
                SelectField::make('certificate_language')->label(__('لغة الشهادة'))->half()
                    ->options(['course' => __('لغة الكورس'), 'student' => __('لغة الطالب')])->default('student'),
                TextField::make('certificate_signature')->label(__('صورة التوقيع'))->url()->half(),
                TextField::make('certificate_seal')->label(__('صورة الختم'))->url()->half(),
            ]),

            Section::make(__('التحفيز'))->fields([
                SwitchField::make('badges')->label(__('الشارات'))->default(true),
                SwitchField::make('leaderboard')->label(__('لوحة الصدارة'))->default(false)
                    ->hint(__('تحفّز المتقدّمين وقد تُحبط المتأخّرين — فعّلها بوعي.')),
                SwitchField::make('streaks')->label(__('سلاسل الإنجاز اليومية'))->default(true),
                NumberField::make('points_per_lesson')->label(__('نقاط إكمال درس'))->range(0, 1000)->half()->default(10),
                NumberField::make('points_per_quiz')->label(__('نقاط اجتياز اختبار'))->range(0, 1000)->half()->default(50),
            ]),
        ];
    }
}
