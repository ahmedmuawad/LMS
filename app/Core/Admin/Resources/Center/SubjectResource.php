<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Center;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\ColorField;
use App\Core\Admin\Fields\IconField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Resource;
use App\Modules\Center\Models\Stage;
use App\Modules\Center\Models\Subject;
use Illuminate\Contracts\Database\Eloquent\Builder;

/** المواد التي تُدرَّس — واللون والأيقونة يميّزانها في الجدول. */
final class SubjectResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::CENTER_MANAGE;
    }

    public function model(): string
    {
        return Subject::class;
    }

    public function label(): string
    {
        return __('المواد');
    }

    public function singularLabel(): string
    {
        return __('مادة');
    }

    public function query(): Builder
    {
        return Subject::query()->with('stage')->withCount('groups');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('icon')->label('')
                ->using(fn ($v): string => (string) ($v ?: '·')),
            TextColumn::make('name')->label(__('المادة'))->searchable()->wrap(),
            TextColumn::make('stage_id')->label(__('المرحلة'))
                ->using(fn ($v, Subject $s): string => (string) ($s->stage?->name ?? __('كل المراحل'))),
            NumberColumn::make('groups_count')->label(__('المجموعات')),
            BooleanColumn::make('is_active')->label(__('فعّالة')),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('المادة'))->fields([
                TranslatableField::make('name')->label(__('الاسم'))->required(),
                SelectField::make('stage_id')->label(__('المرحلة'))->half()
                    ->options(Stage::query()->orderBy('position')->get()
                        ->mapWithKeys(fn (Stage $s): array => [$s->getKey() => (string) $s->name])->all())
                    ->placeholder(__('كل المراحل'))
                    ->hint(__('اتركها فارغة إن كانت المادة لكل المراحل.')),
                IconField::make('icon')->label(__('الأيقونة'))->half(),
                ColorField::make('color')->label(__('اللون'))->half()
                    ->hint(__('يميّز حصص المادة في الجدول.')),
                SwitchField::make('is_active')->label(__('فعّالة'))->default(true),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا مواد بعد'),
            'body' => __('أضف ما تُدرّسه — مادة واحدة تكفي لتبدأ.'),
        ];
    }
}
