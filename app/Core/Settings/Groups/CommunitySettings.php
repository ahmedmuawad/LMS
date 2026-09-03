<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Settings\SettingsGroup;

final class CommunitySettings extends SettingsGroup
{
    public function key(): string
    {
        return 'community';
    }

    public function label(): string
    {
        return __('المجتمع والتقييمات');
    }

    public function icon(): string
    {
        return '◍';
    }

    public function module(): ?string
    {
        return 'community';
    }

    public function description(): ?string
    {
        return __('النقاش والأسئلة والتقييمات واعتدالها.');
    }

    public function sections(): array
    {
        return [
            Section::make(__('النقاش والأسئلة'))
                ->description(__('الطالب الذي يجد من يسأله يكمل الكورس، والذي لا يجد يتوقّف عند أول عائق.'))
                ->fields([
                    SwitchField::make('discussions')->label(__('تفعيل النقاش داخل الكورسات'))->default(true),
                    SelectField::make('who_can_ask')->label(__('من يسأل'))->half()
                        ->options([
                            'enrolled' => __('المسجّلون في الكورس'),
                            'any_user' => __('أي مستخدم مسجّل'),
                        ])->default('enrolled'),
                    SwitchField::make('moderate_questions')->label(__('مراجعة الأسئلة قبل النشر'))->default(false)
                        ->hint(__('المراجعة تُبطئ النقاش — فعّلها إن كثر السبام فقط.')),
                    SwitchField::make('votes')->label(__('التصويت على الأسئلة والإجابات'))->default(true),
                    SwitchField::make('notify_instructor')->label(__('تنبيه المدرّس بكل سؤال'))->default(true),
                    NumberField::make('answer_within_hours')->label(__('هدف الردّ خلال'))->suffix(__('ساعة'))
                        ->range(1, 168)->half()->default(24)
                        ->hint(__('يُقاس في التقارير، ولا يُرسل للطالب.')),
                ]),

            Section::make(__('التقييمات'))->fields([
                SwitchField::make('reviews')->label(__('تفعيل التقييمات'))->default(true),
                SelectField::make('who_can_review')->label(__('من يقيّم'))->half()
                    ->options([
                        'purchased' => __('من اشترى فقط'),
                        'completed' => __('من أتمّ الكورس'),
                        'enrolled' => __('كل مسجّل'),
                    ])->default('purchased'),
                SwitchField::make('moderate_reviews')->label(__('مراجعة التقييم قبل النشر'))->default(true),
                SwitchField::make('auto_approve_high')->label(__('نشر التقييم العالي تلقائياً'))->default(false)
                    ->hint(__('أربع نجوم فأكثر — يقلّل العبء ويُبقي المراجعة على السلبي.')),
                SwitchField::make('allow_reply')->label(__('ردّ المدرّس على التقييم'))->default(true),
                NumberField::make('request_after_days')->label(__('طلب التقييم بعد'))->suffix(__('يوم من الإتمام'))
                    ->range(0, 90)->half()->default(3)->hint(__('صفر يعني بلا طلب.')),
            ]),
        ];
    }
}
