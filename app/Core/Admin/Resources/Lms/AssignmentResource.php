<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Lms;

use App\Core\Access\Ability;
use App\Core\Access\Scope;
use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\CodeField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Resource;
use App\Models\User;
use App\Modules\Lms\Models\Assignment;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class AssignmentResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::ASSIGNMENTS_MANAGE;
    }

    public function scopeFor(Builder $query, ?User $user): Builder
    {
        return app(Scope::class)->byCreator($query, $user);
    }

    public function model(): string
    {
        return Assignment::class;
    }

    public function label(): string
    {
        return __('الواجبات');
    }

    public function singularLabel(): string
    {
        return __('واجب');
    }

    public function query(): Builder
    {
        return Assignment::query()->withCount('submissions');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('title')->label(__('الواجب'))->searchable()->wrap(),

            TextColumn::make('max_marks')->label(__('الدرجة'))->mono()->align('end')
                ->using(fn ($v, Assignment $a): string => rtrim(rtrim((string) $v, '0'), '.').' / '
                    .rtrim(rtrim((string) $a->passing_marks, '0'), '.').' '.__('للنجاح')),

            TextColumn::make('due_days')->label(__('مهلة التسليم'))->mono()->align('end')
                ->using(fn ($v): string => trans_choice(
                    '{1} يوم|{2} يومان|[3,10] :count أيام|[11,*] :count يوماً',
                    (int) $v,
                    ['count' => (int) $v],
                )),

            BooleanColumn::make('allow_late')->label(__('يقبل المتأخّر')),

            NumberColumn::make('submissions_count')->label(__('التسليمات'))->sortable(),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('الواجب'))->fields([
                TranslatableField::make('title')->label(__('العنوان'))->required(),
                TranslatableField::make('instructions')->label(__('التعليمات'))->long(),
            ]),

            Section::make(__('الدرجات'))->fields([
                NumberField::make('max_marks')->label(__('الدرجة العظمى'))->range(1, 1000)->half()->default(100),
                NumberField::make('passing_marks')->label(__('درجة النجاح'))->range(0, 1000)->half()->default(50),
            ]),

            Section::make(__('التسليم'))
                ->description(__('خصم التأخير يُطبَّق على ما استحقّه الطالب فعلاً لا على الدرجة العظمى.'))
                ->fields([
                    NumberField::make('due_days')->label(__('المهلة'))->suffix(__('يوم'))
                        ->range(1, 365)->half()->default(7),
                    SwitchField::make('allow_late')->label(__('اقبل التسليم المتأخّر'))->default(true),
                    NumberField::make('late_penalty_percent')->label(__('خصم التأخير'))->suffix('%')
                        ->range(0, 100)->half()->default(0),
                    NumberField::make('max_resubmissions')->label(__('مرات إعادة التسليم'))
                        ->range(0, 10)->half()->default(1),
                ]),

            Section::make(__('المرفقات'))->fields([
                NumberField::make('max_file_mb')->label(__('أقصى حجم للملف'))->suffix('MB')
                    ->range(1, 500)->half()->default(25),
                CodeField::make('allowed_extensions')->label(__('الامتدادات المسموحة'))->json()->rows(3)
                    ->hint(__('قائمة نصوص مثل: ["pdf","docx","zip"] — الفراغ يعني الافتراضي.')),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا واجبات بعد'),
            'body' => __('الواجب يُضاف إلى المنهج من محرّر الكورس بعد إنشائه هنا.'),
        ];
    }
}
