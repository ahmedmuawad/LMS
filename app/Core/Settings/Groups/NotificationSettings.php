<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Access\Ability;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\PasswordField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Settings\SettingsGroup;

final class NotificationSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'notifications';
    }

    public function label(): string
    {
        return __('الإشعارات والبريد');
    }

    public function icon(): string
    {
        return '📧';
    }

    public function description(): ?string
    {
        return __('مزوّدو القنوات ومفاتيحهم. مصفوفة الأحداث لها شاشتها المستقلة.');
    }

    public function ability(): string
    {
        return Ability::NOTIFICATIONS_MANAGE;
    }

    public function sections(): array
    {
        return [
            Section::make(__('البريد'))->fields([
                SelectField::make('mail_provider')->label(__('المزوّد'))->half()
                    ->options([
                        'smtp' => 'SMTP',
                        'ses' => 'Amazon SES',
                        'postmark' => 'Postmark',
                        'resend' => 'Resend',
                        'log' => __('تسجيل فقط — للتجربة'),
                    ])->default('smtp'),
                TextField::make('from_name')->label(__('اسم المرسل'))->half(),
                TextField::make('from_email')->label(__('بريد المرسل'))->email()->half(),
                TextField::make('reply_to')->label(__('الرد على'))->email()->half(),
                TextField::make('smtp_host')->label(__('خادم SMTP'))->half(),
                NumberField::make('smtp_port')->label(__('المنفذ'))->range(1, 65535)->half()->default(587),
                TextField::make('smtp_username')->label(__('اسم المستخدم'))->half(),
                PasswordField::make('smtp_password')->label(__('كلمة المرور'))->half(),
                SelectField::make('smtp_encryption')->label(__('التشفير'))->half()
                    ->options(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => __('بلا')])->default('tls'),
                PasswordField::make('provider_api_key')->label(__('مفتاح مزوّد البريد'))->half(),
            ]),

            Section::make(__('الرسائل النصية'))->fields([
                SelectField::make('sms_provider')->label(__('المزوّد'))->half()
                    ->options([
                        'none' => __('معطّل'),
                        'twilio' => 'Twilio',
                        'vodafone' => 'Vodafone Egypt',
                        'unifonic' => 'Unifonic',
                        'msegat' => 'مسجات',
                    ])->default('none'),
                TextField::make('sms_sender')->label(__('اسم المرسل'))->half(),
                TextField::make('sms_key')->label(__('المعرّف'))->half(),
                PasswordField::make('sms_secret')->label(__('السرّ'))->half(),
            ]),

            Section::make(__('واتساب'))
                ->description(__('يفوق البريد في الوصول لدى أولياء الأمور — القوالب تُعتمد من ميتا مسبقاً.'))
                ->fields([
                    SwitchField::make('whatsapp_enabled')->label(__('تفعيل واتساب'))->default(false),
                    TextField::make('whatsapp_phone_id')->label(__('معرّف رقم الهاتف'))->half(),
                    TextField::make('whatsapp_business_id')->label(__('معرّف الحساب التجاري'))->half(),
                    PasswordField::make('whatsapp_token')->label(__('رمز الوصول الدائم'))->half(),
                    PasswordField::make('whatsapp_verify_token')->label(__('رمز تحقق الـ Webhook'))->half(),
                ]),

            Section::make(__('إشعارات المتصفح'))->fields([
                SwitchField::make('web_push_enabled')->label(__('تفعيل Web Push'))->default(false),
                TextField::make('vapid_public')->label(__('مفتاح VAPID العام'))->half(),
                PasswordField::make('vapid_private')->label(__('مفتاح VAPID الخاص'))->half(),
            ]),

            Section::make(__('السلوك العام'))->fields([
                SwitchField::make('user_preferences')->label(__('السماح للمستخدم بالتحكم في إشعاراته'))->default(true),
                NumberField::make('digest_hour')->label(__('ساعة إرسال الملخّص اليومي'))->range(0, 23)->half()->default(9),
                SwitchField::make('quiet_hours')->label(__('احترام ساعات الهدوء'))->default(true),
                NumberField::make('quiet_from')->label(__('من الساعة'))->range(0, 23)->half()->default(22),
                NumberField::make('quiet_to')->label(__('حتى الساعة'))->range(0, 23)->half()->default(8),
            ]),
        ];
    }
}
