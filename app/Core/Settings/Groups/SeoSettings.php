<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\CodeField;
use App\Core\Admin\Fields\MultiSelectField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Settings\SettingsGroup;

/** وثيقة 06 — بلجن السيو الداخلي. */
final class SeoSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'seo';
    }

    public function label(): string
    {
        return __('السيو');
    }

    public function icon(): string
    {
        return '🔍';
    }

    public function module(): ?string
    {
        return 'seo';
    }

    public function description(): ?string
    {
        return __('ما تراه محرّكات البحث ومنصّات المشاركة.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('العناوين والأوصاف'))
                ->description(__('المتغيّرات: {title} · {site} · {separator} · {category} · {instructor}'))
                ->fields([
                    TextField::make('title_template')->label(__('قالب عنوان الصفحة'))->default('{title} {separator} {site}'),
                    SelectField::make('separator')->label(__('الفاصل'))->half()
                        ->options(['|' => '|', '-' => '-', '·' => '·', '—' => '—', '»' => '»'])->default('|'),
                    TranslatableField::make('default_description')->label(__('الوصف الافتراضي'))->long(),
                    TextField::make('course_title_template')->label(__('قالب عنوان الكورس'))
                        ->default('{title} {separator} {instructor} {separator} {site}'),
                    TextField::make('blog_title_template')->label(__('قالب عنوان المقال'))
                        ->default('{title} {separator} {site}'),
                ]),

            Section::make(__('الفهرسة'))->fields([
                SwitchField::make('indexable')->label(__('السماح للمحرّكات بالفهرسة'))->default(true)
                    ->hint(__('أطفئه على نسخة التجربة فقط — إطفاؤه على موقع حيّ يمحوه من جوجل.')),
                MultiSelectField::make('noindex_types')->label(__('استثناء من الفهرسة'))
                    ->options([
                        'search' => __('صفحات البحث'),
                        'cart' => __('السلة والدفع'),
                        'account' => __('صفحات الحساب'),
                        'tags' => __('صفحات الوسوم'),
                        'paginated' => __('الصفحات المرقّمة بعد الأولى'),
                    ])->default(['search', 'cart', 'account']),
                SwitchField::make('sitemap')->label(__('خريطة موقع XML'))->default(true),
                NumberField::make('sitemap_per_file')->label(__('روابط لكل ملف خريطة'))
                    ->range(100, 50000)->half()->default(5000),
                SwitchField::make('breadcrumbs_schema')->label(__('بيانات منظّمة لمسار التنقّل'))->default(true),
                SwitchField::make('course_schema')->label(__('بيانات منظّمة للكورس (Course)'))->default(true),
                SwitchField::make('faq_schema')->label(__('بيانات منظّمة للأسئلة الشائعة'))->default(true),
                CodeField::make('robots_txt')->label(__('محتوى robots.txt'))->rows(8),
            ]),

            Section::make(__('المشاركة الاجتماعية'))->fields([
                TextField::make('og_image')->label(__('صورة المشاركة الافتراضية'))->url()
                    ->hint(__('1200×630 بكسل — أي مقاس آخر يُقصّ.')),
                TextField::make('twitter_site')->label(__('حساب X'))->half()->placeholder('@handle'),
                SelectField::make('twitter_card')->label(__('نوع بطاقة X'))->half()
                    ->options(['summary' => __('ملخّص'), 'summary_large_image' => __('ملخّص بصورة كبيرة')])
                    ->default('summary_large_image'),
            ]),

            Section::make(__('التحقّق من الملكية'))->fields([
                TextField::make('google_verification')->label(__('Google Search Console'))->half(),
                TextField::make('bing_verification')->label(__('Bing Webmaster'))->half(),
                TextField::make('yandex_verification')->label(__('Yandex'))->half(),
                TextField::make('pinterest_verification')->label(__('Pinterest'))->half(),
            ]),

            Section::make(__('الروابط'))->fields([
                SwitchField::make('canonical')->label(__('رابط قانوني لكل صفحة'))->default(true),
                SwitchField::make('hreflang')->label(__('روابط hreflang بين اللغات'))->default(true),
                SwitchField::make('redirect_old_slugs')->label(__('تحويل الروابط القديمة تلقائياً عند تغيير المُعرِّف'))
                    ->default(true)->hint(__('يحمي ترتيبك في البحث عند إعادة التسمية.')),
                SwitchField::make('trailing_slash')->label(__('شرطة مائلة في نهاية الرابط'))->default(false),
            ]),
        ];
    }
}
