<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Lms;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\IconField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class TaxonomyResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::TAXONOMIES_MANAGE;
    }

    public function model(): string
    {
        return Taxonomy::class;
    }

    public function label(): string
    {
        return __('التصنيفات');
    }

    public function singularLabel(): string
    {
        return __('تصنيف');
    }

    public function query(): Builder
    {
        return Taxonomy::query()->with('parent');
    }

    public function defaultSort(): array
    {
        return ['position', 'asc'];
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('الاسم'))->searchable()->wrap()->description('slug'),

            BadgeColumn::make('type')->label(__('النوع'))->sortable()
                ->labels(array_map(fn (string $l): string => __($l), Taxonomy::TYPES)),

            TextColumn::make('parent_id')->label(__('يتبع'))
                ->using(fn ($v, Taxonomy $t): string => (string) ($t->parent?->name ?? '—')),

            TextColumn::make('position')->label(__('الترتيب'))->mono()->align('end')->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('type')->label(__('النوع'))
                ->options(array_map(fn (string $l): string => __($l), Taxonomy::TYPES)),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('التصنيف'))->fields([
                TranslatableField::make('name')->label(__('الاسم'))->required(),
                SelectField::make('type')->label(__('النوع'))->half()->required()
                    ->options(array_map(fn (string $l): string => __($l), Taxonomy::TYPES))
                    ->default('category'),
                TextField::make('slug')->label(__('الرابط'))->half()->required()
                    ->rules(['alpha_dash', 'max:160']),
                SelectField::make('parent_id')->label(__('يتبع'))->half()
                    ->options(Taxonomy::get()
                        ->mapWithKeys(fn (Taxonomy $t): array => [$t->getKey() => (string) $t->name])->all()),
                NumberField::make('position')->label(__('الترتيب'))->range(0, 999)->half()->default(0),
                IconField::make('icon')->label(__('الأيقونة'))->half()
                    ->hint(__('رمز واحد أو إيموجي.')),
                TranslatableField::make('description')->label(__('الوصف'))->long(),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا تصنيفات بعد'),
            'body' => __('الأقسام والمستويات وتصنيفات بنك الأسئلة كلها من هنا.'),
        ];
    }
}
