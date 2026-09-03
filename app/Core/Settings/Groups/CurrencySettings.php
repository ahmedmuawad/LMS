<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\MultiSelectField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Settings\SettingsGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CurrencySettings extends SettingsGroup
{
    public function key(): string
    {
        return 'currency';
    }

    public function label(): string
    {
        return __('العملات والضرائب');
    }

    public function icon(): string
    {
        return '💰';
    }

    public function description(): ?string
    {
        return __('كيف تُعرض الأسعار وكيف تُحسب الضريبة.');
    }

    /** @return array<string, string> */
    private function currencies(): array
    {
        $connection = config('tenancy.database.central_connection');

        if (! Schema::connection($connection)->hasTable('currencies')) {
            return ['EGP' => 'جنيه مصري', 'SAR' => 'ريال سعودي', 'USD' => 'دولار'];
        }

        return DB::connection($connection)
            ->table('currencies')->where('is_active', true)
            ->pluck('code', 'code')->all();
    }

    public function sections(): array
    {
        return [
            Section::make(__('العملات'))->fields([
                SelectField::make('default')->label(__('العملة الافتراضية'))->half()
                    ->options($this->currencies())->default('EGP'),
                MultiSelectField::make('enabled')->label(__('العملات المفعّلة'))
                    ->options($this->currencies())->columns(3)->default(['EGP']),
                SelectField::make('rates_source')->label(__('مصدر أسعار الصرف'))->half()
                    ->options([
                        'manual' => __('يدوي — أسعار مثبّتة'),
                        'daily_api' => __('تحديث يومي من مزوّد'),
                    ])->default('manual')
                    ->hint(__('أسعار الباقات مثبّتة لكل عملة ولا تُحوَّل (ADR-014).')),
            ]),

            Section::make(__('شكل السعر'))->fields([
                SelectField::make('symbol_position')->label(__('موضع الرمز'))->half()
                    ->options(['before' => __('قبل المبلغ'), 'after' => __('بعد المبلغ')])->default('after'),
                SelectField::make('decimals')->label(__('عدد الخانات العشرية'))->half()
                    ->options(['0' => '0', '2' => '2', '3' => '3'])->default('2'),
                SwitchField::make('thousands_separator')->label(__('فاصل الآلاف'))->default(true),
                SelectField::make('smart_rounding')->label(__('التقريب الذكي'))->half()
                    ->options([
                        'off' => __('بلا تقريب'),
                        'nine' => __('ينتهي بـ 9 (99 · 199)'),
                        'ninety_nine' => __('ينتهي بـ 99 (999 · 1999)'),
                    ])->default('off'),
            ]),

            Section::make(__('الضريبة'))
                ->description(__('نِسَب الضريبة لكل دولة تُدار من شاشة الدول والعملات في اللوحة العليا.'))
                ->fields([
                    SwitchField::make('tax_enabled')->label(__('تفعيل الضريبة'))->default(false),
                    SelectField::make('prices_include_tax')->label(__('الأسعار المعروضة'))->half()
                        ->options([
                            'inclusive' => __('شاملة الضريبة'),
                            'exclusive' => __('غير شاملة — تُضاف عند الدفع'),
                        ])->default('inclusive'),
                    NumberField::make('default_rate')->label(__('النسبة الافتراضية'))->suffix('%')
                        ->range(0, 100)->half()->default(14),
                    TextField::make('tax_number')->label(__('الرقم الضريبي'))->half(),
                    TranslatableField::make('company_name')->label(__('اسم الشركة على الفاتورة')),
                    TranslatableField::make('company_address')->label(__('عنوان الشركة على الفاتورة'))->long(),
                ]),
        ];
    }
}
