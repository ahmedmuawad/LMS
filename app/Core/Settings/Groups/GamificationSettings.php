<?php

declare(strict_types=1);

namespace App\Core\Settings\Groups;

use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Settings\SettingsGroup;

final class GamificationSettings extends SettingsGroup
{
    public function key(): string
    {
        return 'gamification';
    }

    public function label(): string
    {
        return __('التحفيز والنقاط');
    }

    public function icon(): string
    {
        return '★';
    }

    public function module(): ?string
    {
        return 'gamification';
    }

    public function description(): ?string
    {
        return __('النقاط والشارات والمستويات ولوحة الصدارة.');
    }

    public function sections(): array
    {
        $rules = [];

        foreach (config('gamification.rules', []) as $key => $rule) {
            $rules[] = NumberField::make('points.'.$key)
                ->label(__($rule['label']))
                ->suffix(__('نقطة'))
                ->range(0, 1000)
                ->half()
                ->default((int) $rule['points'])
                ->hint($rule['once'] ? __('مرة واحدة لكل مصدر.') : ($rule['daily'] > 0
                    ? __('حتى :count مرات يومياً.', ['count' => $rule['daily']])
                    : null));
        }

        return [
            Section::make(__('العام'))->fields([
                SwitchField::make('enabled')->label(__('تفعيل النقاط'))->default(true),
                SwitchField::make('badges')->label(__('تفعيل الشارات'))->default(true),
                SwitchField::make('show_in_profile')->label(__('إظهار النقاط في ملف الطالب'))->default(true),
                SwitchField::make('streaks')->label(__('تتبّع الأيام المتتابعة'))->default(true),
            ]),

            Section::make(__('لوحة الصدارة'))
                ->description(__('اللوحة تحفّز المتصدّرين وتُحبط من في القاع — لذا تُضبط لا تُترك.'))
                ->fields([
                    SwitchField::make('leaderboard')->label(__('تفعيل لوحة الصدارة'))->default(true),
                    SelectField::make('leaderboard_scope')->label(__('نطاق اللوحة'))->half()
                        ->options([
                            'all' => __('المنصّة كلّها'),
                            'course' => __('داخل كل كورس'),
                            'group' => __('داخل المجموعة'),
                        ])->default('course'),
                    SelectField::make('leaderboard_period')->label(__('المدة'))->half()
                        ->options([
                            'week' => __('أسبوعية — تعطي فرصة جديدة كل أسبوع'),
                            'month' => __('شهرية'),
                            'all' => __('منذ البداية'),
                        ])->default('week'),
                    NumberField::make('leaderboard_size')->label(__('عدد الظاهرين'))->range(3, 100)->half()->default(10),
                    SwitchField::make('leaderboard_anonymous')->label(__('إخفاء أسماء من هم خارج العشرة الأوائل'))->default(true),
                ]),

            Section::make(__('قيم النقاط'))
                ->description(__('صفر يعطّل القاعدة بلا أن يخفيها.'))
                ->fields($rules),
        ];
    }
}
