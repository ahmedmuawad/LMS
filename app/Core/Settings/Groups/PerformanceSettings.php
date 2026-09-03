<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\MultiSelectField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Fields\TextField;
use App\Core\Settings\SettingsGroup;

/** وثيقة 06 — ميزانية الأداء مُلزِمة، وهذه مقابضها. */
final class PerformanceSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'performance';
    }

    public function label(): string
    {
        return __('الأداء');
    }

    public function icon(): string
    {
        return '⚡';
    }

    public function description(): ?string
    {
        return __('الكاش والصور والشبكة. الافتراضيات هنا هي ما يحقّق ميزانية الأداء.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('الكاش'))->fields([
                MultiSelectField::make('cached_pages')->label(__('صفحات تُخزَّن مؤقتاً'))
                    ->options([
                        'home' => __('الرئيسية'),
                        'course_list' => __('قائمة الكورسات'),
                        'course_page' => __('صفحة الكورس'),
                        'blog' => __('المدونة'),
                        'pages' => __('الصفحات الثابتة'),
                    ])->default(['home', 'course_list', 'course_page', 'blog', 'pages']),
                NumberField::make('page_cache_minutes')->label(__('مدة كاش الصفحة'))->suffix(__('دقيقة'))
                    ->range(1, 10080)->half()->default(60),
                SwitchField::make('cache_warm')->label(__('تسخين الكاش بعد كل نشر'))->default(true)
                    ->hint(__('أول زائر لا يدفع ثمن بناء الصفحة.')),
            ]),

            Section::make(__('الصور والوسائط'))->fields([
                SelectField::make('image_format')->label(__('صيغة الصور'))->half()
                    ->options(['webp' => 'WebP', 'avif' => 'AVIF', 'both' => __('AVIF مع WebP احتياطياً')])
                    ->default('both'),
                NumberField::make('image_quality')->label(__('جودة الضغط'))->suffix('%')->range(40, 100)->half()->default(82),
                SwitchField::make('lazy_load')->label(__('تحميل الصور عند الحاجة'))->default(true),
                SwitchField::make('responsive_images')->label(__('نسخ متعدّدة المقاسات'))->default(true),
                TextField::make('cdn_url')->label(__('نطاق الـ CDN'))->url()->half(),
            ]),

            Section::make(__('الشبكة والأصول'))->fields([
                SwitchField::make('minify')->label(__('تصغير HTML و CSS و JS'))->default(true),
                TextareaField::make('preconnect')->label(__('نطاقات Preconnect'))
                    ->hint(__('نطاق في كل سطر — للنطاقات الحرجة فقط، فالإفراط يضرّ.')),
                TextareaField::make('preload')->label(__('ملفات Preload')),
                SwitchField::make('http_push')->label(__('Early Hints (103)'))->default(false),
            ]),

            Section::make(__('التشغيل'))->fields([
                SwitchField::make('octane')->label(__('تشغيل Octane'))->default(false)
                    ->hint(__('يحتاج مراجعة الحالة العامة قبل تفعيله على منصة مزدحمة.')),
                NumberField::make('queue_workers')->label(__('عدد عمّال الطوابير'))->range(1, 64)->half()->default(4),
                SelectField::make('schedule_frequency')->label(__('تكرار المهام المجدولة'))->half()
                    ->options(['minute' => __('كل دقيقة'), 'five' => __('كل ٥ دقائق'), 'fifteen' => __('كل ربع ساعة')])
                    ->default('minute'),
            ]),
        ];
    }
}
