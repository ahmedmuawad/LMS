<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Access\Ability;
use App\Core\Admin\Fields\CodeField;
use App\Core\Admin\Fields\ColorField;
use App\Core\Admin\Fields\ImageField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Settings\SettingsGroup;
use App\Core\Theming\ThemeManager;

final class AppearanceSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'appearance';
    }

    public function label(): string
    {
        return __('المظهر والهوية');
    }

    public function icon(): string
    {
        return '🎨';
    }

    public function description(): ?string
    {
        return __('ألوان منصّتك وتخطيطها. كل لون يُفحص تباينه قبل الحفظ.');
    }

    public function ability(): string
    {
        return Ability::APPEARANCE_MANAGE;
    }

    public function sections(): array
    {
        return [
            Section::make(__('الثيم'))->fields([
                SelectField::make('theme')->label(__('الثيم'))->half()
                    ->options(collect(app(ThemeManager::class)->all())
                        ->mapWithKeys(fn (array $m, string $key): array => [
                            $key => ($m['icon'] ?? '').' '.($m['name'][app()->getLocale()] ?? $m['name']['ar'] ?? $key),
                        ])->all())
                    ->hint(__('لكل نمط ثيمه المناسب — واختيارك يبقى قائماً بعد أي تحديث.')),
                SelectField::make('dark_mode')->label(__('الوضع الداكن'))->half()
                    ->options([
                        'system' => __('حسب إعداد الجهاز'),
                        'toggle' => __('زر يدوي مع احترام الجهاز'),
                        'light' => __('فاتح دائماً'),
                        'dark' => __('داكن دائماً'),
                    ])->default('toggle'),
            ]),

            Section::make(__('الألوان'))
                ->description(__('نضبط درجات كل لون آلياً لتبقى نسبة التباين ضمن WCAG AA في الوضعين.'))
                ->fields([
                    ColorField::make('primary')->label(__('اللون الأساسي'))->half()->default('#1F6FEB'),
                    ColorField::make('accent')->label(__('لون التمييز'))->half()->default('#8B5CF6'),
                    ColorField::make('success')->label(__('النجاح'))->half()->default('#15803D'),
                    ColorField::make('danger')->label(__('الخطر'))->half()->default('#B91C1C'),
                ]),

            Section::make(__('الشكل'))->fields([
                SelectField::make('radius')->label(__('نصف قطر الحواف'))->half()
                    ->options(['none' => __('حاد'), 'sm' => __('خفيف'), 'md' => __('متوسط'), 'lg' => __('كبير'), 'full' => __('دائري')])
                    ->default('md'),
                SelectField::make('density')->label(__('كثافة الواجهة'))->half()
                    ->options(['comfortable' => __('مريحة'), 'compact' => __('مضغوطة')])->default('comfortable'),
                SelectField::make('header_layout')->label(__('تخطيط الرأس'))->half()
                    ->options([
                        'classic' => __('كلاسيكي — شعار يمين وقائمة'),
                        'centered' => __('شعار في المنتصف'),
                        'search_first' => __('بحث بارز في الوسط'),
                        'minimal' => __('مبسّط'),
                    ])->default('classic'),
                SelectField::make('footer_layout')->label(__('تخطيط التذييل'))->half()
                    ->options(['columns' => __('أعمدة'), 'simple' => __('سطر واحد'), 'rich' => __('غنيّ بنشرة بريدية')])
                    ->default('columns'),
                SwitchField::make('sticky_header')->label(__('رأس ثابت عند التمرير'))->default(true),
                ImageField::make('loader_logo')->label(__('شعار شاشة التحميل'))->folder('brand')->ratio('1/1')->half(),
            ]),

            Section::make(__('تخصيص متقدّم'))
                ->description(__('للمدير وحده — خطأ هنا يظهر لكل الزوّار.'))
                ->fields([
                    CodeField::make('custom_css')->label(__('CSS مخصّص'))->rows(10),
                    CodeField::make('custom_js')->label(__('JavaScript مخصّص'))->rows(8),
                ]),
        ];
    }
}
