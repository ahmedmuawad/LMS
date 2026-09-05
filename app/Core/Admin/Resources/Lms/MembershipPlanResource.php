<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Lms;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Resource;
use App\Modules\Lms\Models\MembershipPlan;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * باقات العضوية التي يبيعها المدرّس لطلابه.
 *
 * وهي غير باقة اشتراكه هو في منصّتنا — شيئان يتشابه اسمهما.
 */
final class MembershipPlanResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::COURSES_MANAGE;
    }

    public function feature(): ?string
    {
        return 'subscriptions';
    }

    public function model(): string
    {
        return MembershipPlan::class;
    }

    public function label(): string
    {
        return __('باقات الاشتراك');
    }

    public function singularLabel(): string
    {
        return __('باقة اشتراك');
    }

    public function query(): Builder
    {
        return MembershipPlan::query();
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('الباقة'))->searchable()->sortable(),

            TextColumn::make('price_minor')->label(__('السعر'))->mono()->align('end')->sortable()
                ->using(fn ($v, MembershipPlan $p): string => $p->price()->format()),

            BadgeColumn::make('period')->label(__('الدورة'))
                ->labels(array_map(fn (string $l): string => __($l), MembershipPlan::PERIODS)),

            TextColumn::make('id')->label(__('المشتركون'))->mono()->align('end')
                ->using(fn ($v, MembershipPlan $p): string => (string) $p->memberships()->count()),

            BooleanColumn::make('is_active')->label(__('مفعّلة')),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('الباقة'))->fields([
                TranslatableField::make('name')->label(__('الاسم'))->required(),
                TextField::make('slug')->label(__('المعرّف'))->half()->required(),
                TranslatableField::make('description')->label(__('الوصف'))->long(),
            ]),

            Section::make(__('السعر والدورة'))->fields([
                NumberField::make('price_minor')->label(__('السعر'))->half()
                    ->money((string) (tenant('currency') ?? 'EGP')),
                SelectField::make('currency')->label(__('العملة'))->half()
                    ->options([(string) (tenant('currency') ?? 'EGP') => (string) (tenant('currency') ?? 'EGP')])
                    ->default((string) (tenant('currency') ?? 'EGP')),
                SelectField::make('period')->label(__('الدورة'))->half()
                    ->options(array_map(fn (string $l): string => __($l), MembershipPlan::PERIODS))
                    ->default('month'),
                NumberField::make('trial_days')->label(__('أيام التجربة'))->half()
                    ->range(0, 90)->default(0)
                    ->hint(__('صفر يعني بلا تجربة.')),
            ]),

            Section::make(__('ما تفتحه'))
                ->description(__('«كل الكورسات» هو ما يريده أكثر المدرّسين: باقةٌ واحدة تفتح كل ما تنشره اليوم وغداً.'))
                ->fields([
                    SelectField::make('scope')->label(__('النطاق'))->half()
                        ->options([
                            'all' => __('كل الكورسات المنشورة'),
                            'selected' => __('كورسات مختارة'),
                        ])->default('all'),
                    SwitchField::make('is_active')->label(__('معروضة للطلبة'))->default(true),
                ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا باقات اشتراك'),
            'body' => __('باقةٌ شهرية تفتح كل كورساتك أسهل على الطالب من شراء كل كورس — وأثبت لدخلك.'),
        ];
    }
}
