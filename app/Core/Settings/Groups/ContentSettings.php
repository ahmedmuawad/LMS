<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Settings\SettingsGroup;

final class ContentSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'content';
    }

    public function label(): string
    {
        return __('المحتوى والمدونة');
    }

    public function icon(): string
    {
        return '🧩';
    }

    public function module(): ?string
    {
        return 'blog';
    }

    public function description(): ?string
    {
        return __('سلوك المدونة والصفحات والتعليقات.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('المدونة'))->fields([
                NumberField::make('posts_per_page')->label(__('مقالات في الصفحة'))->range(3, 60)->half()->default(12),
                SwitchField::make('sidebar')->label(__('الشريط الجانبي'))->default(true),
                SwitchField::make('related_posts')->label(__('مقالات ذات صلة'))->default(true),
                SwitchField::make('reading_time')->label(__('وقت القراءة المقدَّر'))->default(true),
                SwitchField::make('author_box')->label(__('صندوق الكاتب'))->default(true),
                SwitchField::make('toc')->label(__('فهرس المحتويات التلقائي'))->default(true),
            ]),

            Section::make(__('التعليقات'))->fields([
                SelectField::make('comments')->label(__('التعليقات'))->half()
                    ->options([
                        'off' => __('معطّلة'),
                        'users' => __('للمسجّلين فقط'),
                        'everyone' => __('للجميع'),
                    ])->default('users'),
                SwitchField::make('moderate')->label(__('مراجعة قبل النشر'))->default(true),
                SwitchField::make('moderate_first_only')->label(__('مراجعة أول تعليق للكاتب فقط'))->default(true)
                    ->hint(__('يقلّل عبء المراجعة بلا فتح الباب للسبام.')),
                NumberField::make('close_after_days')->label(__('إغلاق التعليقات بعد'))->suffix(__('يوم'))
                    ->range(0, 3650)->half()->default(0)->hint(__('صفر يعني بلا إغلاق.')),
            ]),

            Section::make(__('الصفحات الإلزامية'))
                ->description(__('تُنشأ تلقائياً بمحتوى مبدئي قابل للتحرير، ويربطها التذييل.'))
                ->fields([
                    SwitchField::make('page_about')->label(__('من نحن'))->default(true),
                    SwitchField::make('page_contact')->label(__('اتصل بنا'))->default(true),
                    SwitchField::make('page_terms')->label(__('الشروط والأحكام'))->default(true),
                    SwitchField::make('page_privacy')->label(__('سياسة الخصوصية'))->default(true),
                    SwitchField::make('page_refund')->label(__('سياسة الاسترداد'))->default(true),
                    SwitchField::make('page_faq')->label(__('الأسئلة الشائعة'))->default(true),
                    SwitchField::make('page_become_instructor')->label(__('كن مدرّساً'))->default(false),
                    SwitchField::make('page_careers')->label(__('الوظائف'))->default(false),
                ]),
        ];
    }
}
