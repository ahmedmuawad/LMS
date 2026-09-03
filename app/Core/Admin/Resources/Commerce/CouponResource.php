<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Commerce;

use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Commerce\Models\Coupon;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class CouponResource extends Resource
{
    public function model(): string
    {
        return Coupon::class;
    }

    public function label(): string
    {
        return __('الكوبونات');
    }

    public function singularLabel(): string
    {
        return __('كوبون');
    }

    public function query(): Builder
    {
        return Coupon::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('code')->label(__('الكود'))->mono()->searchable()->sortable(),

            BadgeColumn::make('type')->label(__('النوع'))
                ->labels(array_map(fn (string $l): string => __($l), Coupon::TYPES)),

            TextColumn::make('value')->label(__('القيمة'))->mono()->align('end')
                ->using(fn ($v, Coupon $c): string => $c->type === 'percent'
                    ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%'
                    : rtrim(rtrim(number_format((float) $v, 2), '0'), '.')),

            TextColumn::make('used_count')->label(__('الاستخدام'))->mono()->align('end')
                ->using(fn ($v, Coupon $c): string => $c->usage_limit === null ? (string) $v : $v.' / '.$c->usage_limit),

            DateColumn::make('ends_at')->label(__('ينتهي في'))->sortable(),

            BadgeColumn::make('is_active')->label(__('الحالة'))
                ->using(fn ($v, Coupon $c): string => match (true) {
                    ! $c->is_active => __('موقوف'),
                    $c->hasExpired() => __('منتهٍ'),
                    $c->isExhausted() => __('مستنفَد'),
                    ! $c->hasStarted() => __('لم يبدأ'),
                    default => __('فعّال'),
                }),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('type')->label(__('النوع'))
                ->options(array_map(fn (string $l): string => __($l), Coupon::TYPES)),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('الكوبون'))->fields([
                TextField::make('code')->label(__('الكود'))->required()->half()
                    ->rules(['max:48', 'unique:coupons,code'])
                    ->hint(__('يُكتب بيد العميل — اجعله قصيراً بلا حروف تلتبس.')),
                TranslatableField::make('name')->label(__('الاسم الداخلي')),
                SelectField::make('type')->label(__('النوع'))->half()->required()
                    ->options(array_map(fn (string $l): string => __($l), Coupon::TYPES))
                    ->default('percent'),
                NumberField::make('value')->label(__('القيمة'))->range(0, 100000)->half()->required(),
            ]),

            Section::make(__('الشروط'))->fields([
                NumberField::make('min_order_minor')->label(__('حد أدنى للطلب'))->half()
                    ->money((string) (tenant('currency') ?? 'EGP')),
                NumberField::make('max_discount_minor')->label(__('أقصى خصم'))->half()
                    ->money((string) (tenant('currency') ?? 'EGP'))
                    ->hint(__('يحمي هامشك من نسبة كبيرة على طلب كبير.')),
                NumberField::make('usage_limit')->label(__('حد الاستخدام الكلي'))->range(0, 1000000)->half()
                    ->hint(__('اتركه فارغاً لبلا حد.')),
                NumberField::make('usage_limit_per_user')->label(__('حد الاستخدام لكل عميل'))
                    ->range(1, 100)->half()->default(1),
                SwitchField::make('first_order_only')->label(__('لأول طلب فقط')),
            ]),

            Section::make(__('المدة'))->fields([
                TextField::make('starts_at')->label(__('يبدأ في'))->half()->rules(['date'])
                    ->hint(__('بصيغة 2026-09-10 14:00')),
                TextField::make('ends_at')->label(__('ينتهي في'))->half()->rules(['date', 'after:starts_at']),
                SwitchField::make('is_active')->label(__('فعّال'))->default(true),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا كوبونات بعد'),
            'body' => __('الكوبون أداة تسويق — اجعله محدود المدة ليصنع إلحاحاً.'),
        ];
    }
}
