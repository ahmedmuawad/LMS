<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\PasswordField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Settings\SettingsGroup;

final class AnalyticsSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'analytics';
    }

    public function label(): string
    {
        return __('التحليلات والتتبّع');
    }

    public function icon(): string
    {
        return '📊';
    }

    public function description(): ?string
    {
        return __('ما يُرسَل إلى منصّات القياس والإعلان.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('جوجل'))->fields([
                TextField::make('ga4_id')->label(__('معرّف GA4'))->half()->placeholder('G-XXXXXXX'),
                SwitchField::make('ga4_server_side')->label(__('إرسال من الخادم (Measurement Protocol)'))->default(false)
                    ->hint(__('يتجاوز مانعات الإعلانات فتصل بيانات أدقّ.')),
                PasswordField::make('ga4_api_secret')->label(__('سرّ الـ API'))->half(),
                TextField::make('gtm_id')->label(__('معرّف Google Tag Manager'))->half()->placeholder('GTM-XXXXXX'),
                TextField::make('google_ads_id')->label(__('معرّف Google Ads'))->half()->placeholder('AW-XXXXXXX'),
                TextField::make('google_ads_purchase_label')->label(__('تسمية تحويل الشراء'))->half(),
            ]),

            Section::make(__('ميتا'))->fields([
                TextField::make('meta_pixel_id')->label(__('معرّف Meta Pixel'))->half(),
                SwitchField::make('meta_capi')->label(__('Conversions API'))->default(false),
                PasswordField::make('meta_capi_token')->label(__('رمز وصول الـ CAPI'))->half(),
            ]),

            Section::make(__('منصّات أخرى'))->fields([
                TextField::make('tiktok_pixel')->label(__('TikTok Pixel'))->half(),
                TextField::make('snapchat_pixel')->label(__('Snapchat Pixel'))->half(),
                TextField::make('x_pixel')->label(__('X Pixel'))->half(),
                TextField::make('linkedin_partner')->label(__('LinkedIn Insight'))->half(),
                TextField::make('clarity_id')->label(__('Microsoft Clarity'))->half(),
                TextField::make('hotjar_id')->label(__('Hotjar'))->half(),
            ]),

            Section::make(__('تتبّع التجارة'))
                ->description(__('أحداث القمع الكامل: عرض المنتج ← إضافة للسلة ← بدء الدفع ← الشراء.'))
                ->fields([
                    SwitchField::make('ecommerce_tracking')->label(__('تتبّع التجارة المحسّن'))->default(true),
                    SwitchField::make('consent_mode')->label(__('وضع موافقة الكوكيز (Consent Mode v2)'))->default(true)
                        ->hint(__('لا يُرسَل شيء قبل موافقة الزائر — شرط قانوني في أوروبا.')),
                    SwitchField::make('anonymize_ip')->label(__('إخفاء عنوان IP'))->default(true),
                ]),
        ];
    }
}
