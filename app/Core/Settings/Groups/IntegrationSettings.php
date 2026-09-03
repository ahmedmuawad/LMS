<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\PasswordField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Settings\SettingsGroup;

final class IntegrationSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'integrations';
    }

    public function label(): string
    {
        return __('التكاملات');
    }

    public function icon(): string
    {
        return '🔌';
    }

    public function description(): ?string
    {
        return __('ما يربط منصّتك بما حولها: فيديو، اجتماعات، تخزين، تسويق.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('واجهة البرمجة'))->fields([
                SwitchField::make('api_enabled')->label(__('تفعيل REST API'))->default(false),
                NumberField::make('api_rate_limit')->label(__('حد الطلبات في الدقيقة'))->range(10, 10000)->half()->default(60),
                SwitchField::make('webhooks_enabled')->label(__('تفعيل الـ Webhooks'))->default(false),
                NumberField::make('webhook_retries')->label(__('محاولات إعادة الإرسال'))->range(0, 10)->half()->default(3),
            ]),

            Section::make(__('الفيديو'))->fields([
                SelectField::make('video_provider')->label(__('مزوّد الفيديو'))->half()
                    ->options([
                        'bunny' => 'Bunny Stream',
                        'cloudflare' => 'Cloudflare Stream',
                        'vimeo' => 'Vimeo',
                        'youtube' => 'YouTube (غير محمي)',
                        'self' => __('استضافة ذاتية'),
                    ])->default('bunny'),
                TextField::make('video_library_id')->label(__('معرّف المكتبة'))->half(),
                PasswordField::make('video_api_key')->label(__('مفتاح الـ API'))->half(),
                PasswordField::make('video_token_key')->label(__('مفتاح توقيع الروابط'))->half()
                    ->hint(__('به يُوقَّع كل رابط مشاهدة فينتهي بعد دقائق.')),
                TextField::make('video_pull_zone')->label(__('نطاق التوزيع'))->half(),
            ]),

            Section::make(__('الحصص المباشرة'))->fields([
                SwitchField::make('zoom_enabled')->label(__('Zoom'))->default(false),
                TextField::make('zoom_account_id')->label(__('معرّف الحساب'))->half(),
                TextField::make('zoom_client_id')->label(__('معرّف العميل'))->half(),
                PasswordField::make('zoom_client_secret')->label(__('سرّ العميل'))->half(),
                SwitchField::make('meet_enabled')->label(__('Google Meet'))->default(false),
                TextField::make('google_client_id')->label(__('معرّف عميل جوجل'))->half(),
                PasswordField::make('google_client_secret')->label(__('سرّ عميل جوجل'))->half(),
                SwitchField::make('bbb_enabled')->label(__('BigBlueButton'))->default(false),
                TextField::make('bbb_url')->label(__('عنوان الخادم'))->url()->half(),
                PasswordField::make('bbb_secret')->label(__('السرّ المشترك'))->half(),
            ]),

            Section::make(__('التخزين'))->fields([
                SelectField::make('storage_driver')->label(__('مزوّد التخزين'))->half()
                    ->options(['local' => __('القرص المحلي'), 's3' => 'S3', 'spaces' => 'DigitalOcean Spaces', 'r2' => 'Cloudflare R2'])
                    ->default('local'),
                TextField::make('storage_bucket')->label(__('اسم السلة'))->half(),
                TextField::make('storage_region')->label(__('المنطقة'))->half(),
                TextField::make('storage_endpoint')->label(__('العنوان'))->url()->half(),
                TextField::make('storage_key')->label(__('المفتاح'))->half(),
                PasswordField::make('storage_secret')->label(__('السرّ'))->half(),
            ]),

            Section::make(__('التسويق والتنبيهات'))->fields([
                SelectField::make('crm_provider')->label(__('نظام العملاء'))->half()
                    ->options(['none' => __('بلا'), 'mailchimp' => 'Mailchimp', 'brevo' => 'Brevo', 'hubspot' => 'HubSpot', 'zoho' => 'Zoho'])
                    ->default('none'),
                PasswordField::make('crm_api_key')->label(__('مفتاح الـ API'))->half(),
                TextField::make('slack_webhook')->label(__('Slack — رابط الإشعارات'))->url()->half(),
                TextField::make('discord_webhook')->label(__('Discord — رابط الإشعارات'))->url()->half(),
                SwitchField::make('google_calendar')->label(__('مزامنة تقويم جوجل'))->default(false),
                SwitchField::make('zapier')->label(__('Zapier / Make'))->default(false),
            ]),
        ];
    }
}
