<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Settings\SettingsGroup;

final class GrowthSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'growth';
    }

    public function label(): string
    {
        return __('النمو والتسويق');
    }

    public function icon(): string
    {
        return '⇢';
    }

    public function module(): ?string
    {
        return 'funnels';
    }

    public function description(): ?string
    {
        return __('التسويق بالعمولة والتسلسلات التسويقية ومُطلِقاتها.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('التسويق بالعمولة'))
                ->description(__('العمولة تنضج بعد مهلة الاسترداد لا لحظة البيع — وإلا دفعتها على بيع يُسترد.'))
                ->fields([
                    SwitchField::make('affiliates_enabled')->label(__('تفعيل التسويق بالعمولة'))->default(false),
                    SwitchField::make('affiliates_auto_approve')->label(__('قبول المسوّقين تلقائياً'))->default(false),
                    NumberField::make('affiliates_default_rate')->label(__('النسبة العامة'))->suffix('%')
                        ->range(0, 90)->half()->default(10),
                    NumberField::make('affiliates_cookie_days')->label(__('نافذة النسب'))->suffix(__('يوم'))
                        ->range(1, 365)->half()->default(30)
                        ->hint(__('يوم واحد يظلم المسوّق، وتسعون تنسب إليه بيعاً جاء من إعلانك.')),
                    NumberField::make('affiliates_hold_days')->label(__('مهلة نضج العمولة'))->suffix(__('يوم'))
                        ->range(0, 180)->half()->default(14),
                    NumberField::make('affiliates_min_payout')->label(__('أقل مبلغ للصرف'))
                        ->money((string) (tenant('currency') ?? 'EGP'))->half()->default(50000),
                    TranslatableField::make('affiliates_terms')->label(__('شروط البرنامج'))->long(),
                ]),

            Section::make(__('مُطلِقات الحملات'))->fields([
                NumberField::make('cart_abandoned_after_minutes')->label(__('تُعدّ السلة متروكة بعد'))->suffix(__('دقيقة'))
                    ->range(15, 2880)->half()->default(60),
                NumberField::make('idle_after_days')->label(__('يُعدّ الطالب خاملاً بعد'))->suffix(__('يوم'))
                    ->range(1, 90)->half()->default(7),
                NumberField::make('expiring_before_days')->label(__('تنبيه قرب انتهاء الوصول قبل'))->suffix(__('يوم'))
                    ->range(1, 60)->half()->default(7),
                SelectField::make('quiet_campaigns')->label(__('الحملات وساعات الهدوء'))->half()
                    ->options([
                        'respect' => __('تحترمها — تُؤجَّل إلى الصباح'),
                        'ignore' => __('تتجاوزها'),
                    ])->default('respect'),
            ]),

            Section::make(__('حدود الإرسال'))
                ->description(__('الحدّ يحمي سمعة نطاقك: مزوّد البريد يُعاقب المرسل الفجائي قبل أن يعاقبه المستقبِل.'))
                ->fields([
                    NumberField::make('max_campaign_per_user_daily')->label(__('أقصى رسائل حملات للشخص يومياً'))
                        ->range(0, 20)->half()->default(2)->hint(__('صفر يعني بلا حد.')),
                    NumberField::make('max_sends_per_cycle')->label(__('أقصى إرسال في الدورة الواحدة'))
                        ->range(10, 5000)->half()->default(200),
                ]),
        ];
    }
}
