<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Community;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\IconField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Resource;
use App\Modules\Gamification\Models\Badge;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class BadgeResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::GAMIFICATION_MANAGE;
    }

    public function model(): string
    {
        return Badge::class;
    }

    public function label(): string
    {
        return __('الشارات');
    }

    public function singularLabel(): string
    {
        return __('شارة');
    }

    public function query(): Builder
    {
        return Badge::query()->withCount('users');
    }

    public function defaultSort(): array
    {
        return ['position', 'asc'];
    }

    public function columns(): array
    {
        return [
            TextColumn::make('icon')->label(__('الأيقونة')),
            TextColumn::make('name')->label(__('الشارة'))->searchable()->description('key'),

            TextColumn::make('condition_rule')->label(__('الشرط'))
                ->using(fn ($v, Badge $b): string => $v === null
                    ? __('يدوية')
                    : __(config('gamification.rules', [])[$v]['label'] ?? $v).' × '.$b->condition_value),

            NumberColumn::make('users_count')->label(__('من نالوها'))->sortable(),

            BooleanColumn::make('is_active')->label(__('مفعّلة')),
        ];
    }

    public function form(): array
    {
        $rules = ['' => __('يدوية — تُمنح باليد')];

        foreach (config('gamification.rules', []) as $key => $rule) {
            $rules[$key] = __($rule['label']);
        }

        return [
            Section::make(__('الشارة'))->fields([
                TranslatableField::make('name')->label(__('الاسم'))->required(),
                TextField::make('key')->label(__('المفتاح'))->required()->half()
                    ->rules(['alpha_dash', 'max:48', 'unique:badges,key']),
                TranslatableField::make('description')->label(__('الوصف'))->long(),
                IconField::make('icon')->label(__('الأيقونة'))->half()
                    ->hint(__('حرف أو رمز واحد.')),
                SelectField::make('tone')->label(__('اللون'))->half()
                    ->options([
                        'primary' => __('أساسي'), 'success' => __('أخضر'),
                        'accent' => __('مميّز'), 'warning' => __('برتقالي'), 'info' => __('أزرق'),
                    ])->default('primary'),
            ]),

            Section::make(__('شرط المنح'))
                ->description(__('تُفحص الشروط بعد كل قيد نقاط، فتصل الشارة صاحبها من أي طريق تحقّقت.'))
                ->fields([
                    SelectField::make('condition_rule')->label(__('القاعدة'))->half()->options($rules),
                    NumberField::make('condition_value')->label(__('عدد المرات'))->range(1, 1000)->half()->default(1),
                    NumberField::make('position')->label(__('الترتيب'))->range(0, 999)->half()->default(0),
                    SwitchField::make('is_active')->label(__('مفعّلة'))->default(true),
                ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا شارات بعد'),
            'body' => __('الشارات الافتراضية تُنشأ عند التهيئة — أضف ما يخصّ منصّتك.'),
        ];
    }
}
