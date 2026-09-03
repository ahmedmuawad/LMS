<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\MultiSelectField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\PasswordField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Fields\TextField;
use App\Core\Settings\SettingsGroup;

final class SecuritySettings extends SettingsGroup
{
    public function key(): string
    {
        return 'security';
    }

    public function label(): string
    {
        return __('الأمان والخصوصية');
    }

    public function icon(): string
    {
        return '🛡';
    }

    public function description(): ?string
    {
        return __('حماية النماذج والمحتوى، وحقوق المستخدم في بياناته.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('مكافحة الآليات'))->fields([
                SelectField::make('captcha_provider')->label(__('المزوّد'))->half()
                    ->options([
                        'none' => __('معطّل'),
                        'recaptcha_v3' => 'reCAPTCHA v3',
                        'hcaptcha' => 'hCaptcha',
                        'turnstile' => 'Cloudflare Turnstile',
                    ])->default('none'),
                TextField::make('captcha_site_key')->label(__('مفتاح الموقع'))->half(),
                PasswordField::make('captcha_secret')->label(__('المفتاح السرّي'))->half(),
                MultiSelectField::make('captcha_on')->label(__('يُطلب في'))
                    ->options([
                        'register' => __('التسجيل'),
                        'login' => __('الدخول'),
                        'comment' => __('التعليقات'),
                        'contact' => __('نموذج التواصل'),
                        'checkout' => __('الدفع'),
                    ])->default(['register', 'contact']),
            ]),

            Section::make(__('حدود الطلبات والحظر'))->fields([
                NumberField::make('rate_limit_per_minute')->label(__('أقصى طلبات في الدقيقة لكل زائر'))
                    ->range(10, 1000)->half()->default(120),
                TextareaField::make('blocked_ips')->label(__('عناوين IP محظورة'))->hint(__('عنوان في كل سطر.')),
                TextField::make('blocked_countries')->label(__('دول محظورة'))
                    ->hint(__('رموز الدول مفصولة بفواصل — مثل RU,KP.')),
            ]),

            Section::make(__('حماية المحتوى'))
                ->description(__('تُصعّب النسخ ولا تمنعه — الحماية الحقيقية في تشفير الفيديو والعلامة المائية.'))
                ->fields([
                    SwitchField::make('block_copy')->label(__('منع تحديد النص ونسخه'))->default(false),
                    SwitchField::make('block_devtools')->label(__('منع أدوات المطوّر'))->default(false),
                    SwitchField::make('video_watermark')->label(__('علامة مائية باسم الطالب على الفيديو'))->default(true),
                    SwitchField::make('block_screenshot')->label(__('منع لقطة الشاشة في التطبيق'))->default(true),
                ]),

            Section::make(__('السجلّات والتنبيهات'))->fields([
                SwitchField::make('login_log')->label(__('سجلّ الدخول'))->default(true),
                SwitchField::make('new_device_alert')->label(__('تنبيه عند دخول من جهاز جديد'))->default(true),
                NumberField::make('log_retention_days')->label(__('مدة الاحتفاظ بالسجلّات'))->suffix(__('يوم'))
                    ->range(7, 3650)->half()->default(180),
            ]),

            Section::make(__('الخصوصية'))->fields([
                SwitchField::make('gdpr_banner')->label(__('بانر الموافقة'))->default(true),
                SwitchField::make('consent_log')->label(__('سجلّ الموافقات'))->default(true),
                SwitchField::make('data_export')->label(__('تصدير بياناتي'))->default(true),
                SwitchField::make('data_deletion')->label(__('طلب حذف الحساب'))->default(true),
                NumberField::make('deletion_grace_days')->label(__('مهلة التراجع عن الحذف'))->suffix(__('يوم'))
                    ->range(0, 90)->half()->default(14),
                SwitchField::make('minor_guardian_consent')->label(__('اشتراط موافقة ولي الأمر للقُصّر'))->default(true),
            ]),

            Section::make(__('النسخ الاحتياطي'))->fields([
                SelectField::make('backup_frequency')->label(__('التكرار'))->half()
                    ->options(['off' => __('معطّل'), 'daily' => __('يومي'), 'twice_daily' => __('مرتين يومياً'), 'hourly' => __('كل ساعة')])
                    ->default('daily'),
                SelectField::make('backup_destination')->label(__('الوجهة'))->half()
                    ->options(['local' => __('القرص المحلي'), 's3' => 'S3', 'spaces' => 'DigitalOcean Spaces', 'b2' => 'Backblaze B2'])
                    ->default('s3'),
                NumberField::make('backup_retention_days')->label(__('مدة الاحتفاظ'))->suffix(__('يوم'))
                    ->range(1, 3650)->half()->default(30),
                SwitchField::make('backup_alert_on_failure')->label(__('تنبيه عند فشل النسخ'))->default(true),
            ]),
        ];
    }
}
