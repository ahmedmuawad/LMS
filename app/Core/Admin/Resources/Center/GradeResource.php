<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Center;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Center\Models\Grade;
use App\Modules\Center\Models\Stage;
use Illuminate\Contracts\Database\Eloquent\Builder;

/** الصفوف داخل كل مرحلة: أول ابتدائي … ثالث ثانوي. */
final class GradeResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::CENTER_MANAGE;
    }

    public function model(): string
    {
        return Grade::class;
    }

    public function label(): string
    {
        return __('الصفوف');
    }

    public function singularLabel(): string
    {
        return __('صف');
    }

    public function query(): Builder
    {
        return Grade::query()->with('stage')
            ->orderBy(Stage::query()->select('position')->whereColumn('center_stages.id', 'center_grades.stage_id'))
            ->orderBy('position');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('الصف'))->searchable()->wrap(),
            TextColumn::make('stage_id')->label(__('المرحلة'))
                ->using(fn ($v, Grade $g): string => (string) ($g->stage?->name ?? '—')),
            NumberColumn::make('position')->label(__('الترتيب'))->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('stage_id')->label(__('المرحلة'))->options($this->stages()),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('الصف'))->fields([
                SelectField::make('stage_id')->label(__('المرحلة'))->required()->half()
                    ->options($this->stages())
                    ->hint(__('أضف المرحلة أولاً إن لم تكن في القائمة.')),
                NumberField::make('position')->label(__('الترتيب'))->range(0, 99)->half()->default(0)
                    ->hint(__('رقم الصف داخل مرحلته.')),
                TranslatableField::make('name')->label(__('الاسم'))->required()
                    ->hint(__('كما يقوله ولي الأمر: «رابع ابتدائي».')),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا صفوف بعد'),
            'body' => __('كل مجموعة تتبع صفّاً — أضف الصفوف التي تُدرّس لها.'),
        ];
    }

    /** @return array<int, string> */
    private function stages(): array
    {
        return Stage::query()->orderBy('position')->get()
            ->mapWithKeys(fn (Stage $s): array => [$s->getKey() => (string) $s->name])
            ->all();
    }
}
