<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Center;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Center\Models\Branch;
use App\Modules\Center\Models\Room;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class RoomResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::CENTER_MANAGE;
    }

    public function model(): string
    {
        return Room::class;
    }

    public function label(): string
    {
        return __('القاعات');
    }

    public function singularLabel(): string
    {
        return __('قاعة');
    }

    public function query(): Builder
    {
        return Room::query()->with('branch');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('القاعة'))->searchable()->wrap(),
            TextColumn::make('branch_id')->label(__('الفرع'))
                ->using(fn ($v, Room $r): string => (string) ($r->branch?->name ?? '—')),
            NumberColumn::make('capacity')->label(__('السعة'))->sortable(),
            BooleanColumn::make('is_active')->label(__('متاحة')),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('branch_id')->label(__('الفرع'))
                ->options(Branch::get()->mapWithKeys(fn (Branch $b): array => [$b->getKey() => (string) $b->name])->all()),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('القاعة'))->fields([
                TranslatableField::make('name')->label(__('الاسم'))->required(),
                SelectField::make('branch_id')->label(__('الفرع'))->half()->required()
                    ->options(Branch::get()->mapWithKeys(fn (Branch $b): array => [$b->getKey() => (string) $b->name])->all()),
                NumberField::make('capacity')->label(__('السعة'))->range(1, 500)->half()->default(30)
                    ->hint(__('يُفحص عليها عدد طلاب المجموعة قبل الجدولة.')),
                SwitchField::make('is_active')->label(__('متاحة'))->default(true),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return ['title' => __('لا قاعات بعد'), 'body' => __('بلا قاعات لا يمكن كشف تعارض المواعيد.')];
    }
}
