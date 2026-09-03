<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\CodeField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Settings\SettingsGroup;

final class GeneralSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'general';
    }

    public function label(): string
    {
        return __('عام');
    }

    public function icon(): string
    {
        return '⚙';
    }

    public function description(): ?string
    {
        return __('هوية المنصة وبيانات التواصل وأوضاع التشغيل.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('الهوية'))
                ->description(__('ما يظهر في العنوان وشريط المتصفح ونتائج البحث.'))
                ->fields([
                    TranslatableField::make('site_name')->label(__('اسم الموقع'))->required(),
                    TranslatableField::make('tagline')->label(__('الوصف المختصر'))->long(),
                    TextField::make('logo_light')->label(__('الشعار — خلفية فاتحة'))->half()->url(),
                    TextField::make('logo_dark')->label(__('الشعار — خلفية داكنة'))->half()->url(),
                    TextField::make('logo_mobile')->label(__('شعار الموبايل'))->half()->url(),
                    TextField::make('favicon')->label(__('أيقونة التبويب'))->half()->url(),
                ]),

            Section::make(__('التواصل'))->fields([
                TextField::make('admin_email')->label(__('بريد الإدارة'))->email()->half(),
                TextField::make('phone')->label(__('الهاتف'))->half(),
                TextField::make('whatsapp')->label(__('واتساب'))->half()
                    ->hint(__('بالصيغة الدولية بلا + مثل 201001234567.')),
                TextField::make('map_url')->label(__('رابط الخريطة'))->half()->url(),
                TranslatableField::make('address')->label(__('العنوان'))->long(),
            ]),

            Section::make(__('روابط التواصل الاجتماعي'))
                ->description(__('اترك ما لا تستخدمه فارغاً — الأيقونة لا تظهر إلا بوجود رابط.'))
                ->fields(array_map(
                    fn (array $net): TextField => TextField::make('social_'.$net[0])->label($net[1])->half()->url(),
                    [
                        ['facebook', 'فيسبوك'], ['instagram', 'إنستجرام'], ['x', 'إكس'],
                        ['youtube', 'يوتيوب'], ['tiktok', 'تيك توك'], ['linkedin', 'لينكدإن'],
                        ['telegram', 'تيليجرام'], ['snapchat', 'سناب شات'], ['threads', 'ثريدز'],
                        ['pinterest', 'بينترست'], ['discord', 'ديسكورد'], ['github', 'جيت هَب'],
                    ],
                )),

            Section::make(__('الوقت والتاريخ'))->fields([
                SelectField::make('timezone')->label(__('المنطقة الزمنية'))->half()
                    ->options(collect(timezone_identifiers_list())->mapWithKeys(fn ($tz): array => [$tz => $tz])->all())
                    ->default('Africa/Cairo'),
                SelectField::make('week_start')->label(__('بداية الأسبوع'))->half()
                    ->options(['saturday' => __('السبت'), 'sunday' => __('الأحد'), 'monday' => __('الاثنين')])
                    ->default('saturday'),
                SelectField::make('date_format')->label(__('تنسيق التاريخ'))->half()
                    ->options(['Y-m-d' => '2026-09-03', 'd/m/Y' => '03/09/2026', 'j F Y' => '3 سبتمبر 2026'])
                    ->default('Y-m-d'),
                SelectField::make('time_format')->label(__('تنسيق الوقت'))->half()
                    ->options(['H:i' => '14:30', 'h:i A' => '02:30 PM'])
                    ->default('h:i A'),
            ]),

            Section::make(__('أوضاع التشغيل'))
                ->description(__('وضع الصيانة يقفل الموقع عن الزوّار ويُبقي اللوحة مفتوحة لك.'))
                ->fields([
                    SwitchField::make('maintenance_mode')->label(__('وضع الصيانة'))->default(false),
                    TranslatableField::make('maintenance_message')->label(__('رسالة الصيانة'))->long(),
                    TextareaField::make('maintenance_allowed_ips')->label(__('عناوين IP مسموح لها بالدخول'))
                        ->hint(__('عنوان في كل سطر.')),
                    SwitchField::make('coming_soon')->label(__('وضع «قريباً»'))->default(false),
                    TranslatableField::make('top_bar_message')->label(__('رسالة الشريط العلوي'))->long(),
                    SwitchField::make('cookie_banner')->label(__('بانر الكوكيز'))->default(true),
                    SwitchField::make('debug_mode')->label(__('وضع التصحيح'))->default(false)
                        ->hint(__('يُظهر تفاصيل الأخطاء. لا تتركه مفعّلاً على موقع يعمل.')),
                ]),

            Section::make(__('الرفع'))->fields([
                SelectField::make('max_upload_mb')->label(__('حد حجم الملف المرفوع'))->half()
                    ->options(['5' => '5 MB', '10' => '10 MB', '25' => '25 MB', '50' => '50 MB', '100' => '100 MB', '512' => '512 MB'])
                    ->default('50'),
                CodeField::make('custom_head')->label(__('كود يُضاف داخل <head>'))->rows(6)
                    ->hint(__('للمدير وحده — يُحقن في كل صفحة.')),
            ]),
        ];
    }
}
