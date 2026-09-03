<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\MultiSelectField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Settings\SettingsGroup;

final class LocaleSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'locale';
    }

    public function label(): string
    {
        return __('اللغات والترجمة');
    }

    public function icon(): string
    {
        return '🌐';
    }

    public function description(): ?string
    {
        return __('لغات الواجهة وسلوك الترجمة وشكل الأرقام.');
    }

    /** @return array<string, string> */
    private function locales(): array
    {
        return collect(config('locales.supported', []))
            ->map(fn (array $meta, string $code): string => $meta['native'] ?? $code)
            ->all();
    }

    public function sections(): array
    {
        return [
            Section::make(__('اللغات'))->fields([
                MultiSelectField::make('enabled')->label(__('اللغات المفعّلة'))
                    ->options($this->locales())->default(['ar', 'en']),
                SelectField::make('default')->label(__('اللغة الافتراضية'))->half()
                    ->options($this->locales())->default('ar'),
                SwitchField::make('detect_from_browser')->label(__('الكشف التلقائي من لغة المتصفح'))->default(true),
            ]),

            Section::make(__('سلوك الترجمة'))
                ->description(__('ما يحدث حين ينقص نصّ في لغة العرض.'))
                ->fields([
                    SelectField::make('fallback')->label(__('عند نقص الترجمة'))
                        ->options([
                            'original' => __('اعرض النص باللغة الأصلية'),
                            'hide' => __('أخفِ العنصر'),
                        ])->default('original'),
                    SwitchField::make('ai_suggestions')->label(__('اقتراح ترجمة آلية للمراجعة'))->default(false)
                        ->hint(__('تُقترح ولا تُنشر حتى يعتمدها إنسان.')),
                ]),

            Section::make(__('الأرقام والخطوط'))->fields([
                SelectField::make('numerals')->label(__('نظام الأرقام'))->half()
                    ->options(['western' => __('عربية (123)'), 'eastern' => __('هندية (١٢٣)')])
                    ->default('western'),
                SelectField::make('font_ar')->label(__('خط العربية'))->half()
                    ->options(['ibm-plex-sans-arabic' => 'IBM Plex Sans Arabic', 'cairo' => 'Cairo', 'tajawal' => 'Tajawal', 'noto-kufi' => 'Noto Kufi Arabic'])
                    ->default('ibm-plex-sans-arabic'),
                SelectField::make('font_en')->label(__('خط الإنجليزية'))->half()
                    ->options(['inter' => 'Inter', 'system' => __('خط النظام'), 'ibm-plex-sans' => 'IBM Plex Sans'])
                    ->default('inter'),
            ]),
        ];
    }
}
