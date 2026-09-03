<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Center;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Models\User;
use App\Modules\Center\Models\Branch;
use App\Modules\Center\Models\Grade;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Subject;
use App\Modules\Center\Models\Term;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class GroupResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::CENTER_MANAGE;
    }

    public function model(): string
    {
        return Group::class;
    }

    public function label(): string
    {
        return __('المجموعات');
    }

    public function singularLabel(): string
    {
        return __('مجموعة');
    }

    public function query(): Builder
    {
        return Group::query()->with(['subject', 'grade', 'teacher', 'branch']);
    }

    public function recordUrl(Model $record, string $key): ?string
    {
        return url('/admin/groups/'.$record->getKey());
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('المجموعة'))->searchable()->wrap()->sortable(),

            TextColumn::make('subject_id')->label(__('المادة'))
                ->using(fn ($v, Group $g): string => (string) ($g->subject?->name ?? '—')),

            TextColumn::make('grade_id')->label(__('الصف'))
                ->using(fn ($v, Group $g): string => (string) ($g->grade?->name ?? '—')),

            TextColumn::make('teacher_id')->label(__('المدرّس'))
                ->using(fn ($v, Group $g): string => (string) ($g->teacher?->name ?? '—')),

            TextColumn::make('enrolled_count')->label(__('الطلاب'))->mono()->align('end')->sortable()
                ->using(fn ($v, Group $g): string => $v.' / '.$g->capacity),

            TextColumn::make('price_minor')->label(__('السعر'))->mono()->align('end')
                ->using(fn ($v, Group $g): string => $g->price()->format()
                    .' '.__(Group::PRICE_TYPES[$g->price_type] ?? '')),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['open' => 'success', 'running' => 'primary', 'draft' => 'neutral',
                    'finished' => 'neutral', 'cancelled' => 'danger'])
                ->labels(array_map(fn (string $l): string => __($l), Group::STATUSES)),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), Group::STATUSES)),

            SelectFilter::make('subject_id')->label(__('المادة'))
                ->options(Subject::get()->mapWithKeys(fn (Subject $s): array => [$s->getKey() => (string) $s->name])->all()),

            SelectFilter::make('branch_id')->label(__('الفرع'))
                ->options(Branch::get()->mapWithKeys(fn (Branch $b): array => [$b->getKey() => (string) $b->name])->all()),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('المجموعة'))->fields([
                TranslatableField::make('name')->label(__('الاسم'))->required()
                    ->hint(__('اسم يعرفه الطلاب: «فيزياء ٣ث — سبت ٤م».')),
                SelectField::make('branch_id')->label(__('الفرع'))->half()->required()
                    ->options(Branch::get()->mapWithKeys(fn (Branch $b): array => [$b->getKey() => (string) $b->name])->all()),
                SelectField::make('subject_id')->label(__('المادة'))->half()
                    ->options(Subject::get()->mapWithKeys(fn (Subject $s): array => [$s->getKey() => (string) $s->name])->all()),
                SelectField::make('grade_id')->label(__('الصف'))->half()
                    ->options(Grade::with('stage')->get()
                        ->mapWithKeys(fn (Grade $g): array => [$g->getKey() => trim(($g->stage?->name ?? '').' — '.$g->name, ' —')])->all()),
                SelectField::make('teacher_id')->label(__('المدرّس'))->half()
                    ->options(User::whereIn('role', ['instructor', 'owner', 'admin'])->pluck('name', 'id')->all()),
                SelectField::make('term_id')->label(__('الترم'))->half()
                    ->options(Term::get()->mapWithKeys(fn (Term $t): array => [$t->getKey() => (string) $t->name])->all()),
            ]),

            Section::make(__('السعة والتسعير'))->fields([
                NumberField::make('capacity')->label(__('السعة'))->range(1, 500)->half()->default(25),
                SelectField::make('currency')->label(__('العملة'))->half()->required()
                    ->options([(string) (tenant('currency') ?? 'EGP') => (string) (tenant('currency') ?? 'EGP')])
                    ->default((string) (tenant('currency') ?? 'EGP')),
                NumberField::make('price_minor')->label(__('السعر'))->half()
                    ->money((string) (tenant('currency') ?? 'EGP')),
                SelectField::make('price_type')->label(__('نوع التسعير'))->half()
                    ->options(array_map(fn (string $l): string => __($l), Group::PRICE_TYPES))
                    ->default('monthly'),
            ]),

            Section::make(__('المدة والحالة'))->fields([
                SelectField::make('status')->label(__('الحالة'))->half()
                    ->options(array_map(fn (string $l): string => __($l), Group::STATUSES))->default('open'),
                NumberField::make('start_date')->label(__('تاريخ البدء'))->half()->replaceRules(['nullable', 'date']),
                NumberField::make('end_date')->label(__('تاريخ الانتهاء'))->half()->replaceRules(['nullable', 'date']),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا مجموعات بعد'),
            'body' => __('المجموعة هي وحدة السنتر: مادة ومدرّس وموعد وطلاب.'),
        ];
    }
}
