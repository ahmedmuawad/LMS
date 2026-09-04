<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Lms;

use App\Core\Access\Ability;
use App\Core\Access\Scope;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\ChoicesField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Models\User;
use App\Modules\Lms\Models\Question;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class QuestionResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::QUIZZES_MANAGE;
    }

    public function scopeFor(Builder $query, ?User $user): Builder
    {
        return app(Scope::class)->byCreator($query, $user);
    }

    public function model(): string
    {
        return Question::class;
    }

    public function label(): string
    {
        return __('بنك الأسئلة');
    }

    public function singularLabel(): string
    {
        return __('سؤال');
    }

    public function query(): Builder
    {
        return Question::query()->with('category');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('body')->label(__('السؤال'))->searchable()->wrap(),

            BadgeColumn::make('type')->label(__('النوع'))->sortable()
                ->labels(array_map(fn (string $l): string => __($l), Question::TYPES)),

            BadgeColumn::make('difficulty')->label(__('الصعوبة'))->sortable()
                ->tones(['easy' => 'success', 'medium' => 'info', 'hard' => 'warning'])
                ->labels(array_map(fn (string $l): string => __($l), Question::DIFFICULTIES)),

            TextColumn::make('category_id')->label(__('التصنيف'))
                ->using(fn ($v, Question $q): string => (string) ($q->category?->name ?? '—')),

            TextColumn::make('marks')->label(__('الدرجة'))->mono()->align('end')->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('type')->label(__('النوع'))
                ->options(array_map(fn (string $l): string => __($l), Question::TYPES)),

            SelectFilter::make('difficulty')->label(__('الصعوبة'))
                ->options(array_map(fn (string $l): string => __($l), Question::DIFFICULTIES)),

            SelectFilter::make('category_id')->label(__('التصنيف'))
                ->options(Taxonomy::ofType('question_category')->get()
                    ->mapWithKeys(fn (Taxonomy $t): array => [$t->getKey() => (string) $t->name])->all()),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('السؤال'))
                ->description(__('تُكتب المعادلات بصياغة TeX بين علامتَي $ — مثل $x^2 + 3x - 4 = 0$ — وتُعرض للطالب مضبوطة.'))
                ->fields([
                    TranslatableField::make('body')->label(__('نص السؤال'))->long()->required(),
                    SelectField::make('type')->label(__('النوع'))->half()
                        ->options(array_map(fn (string $l): string => __($l), Question::TYPES))
                        ->default('single_choice'),
                    SelectField::make('difficulty')->label(__('الصعوبة'))->half()
                        ->options(array_map(fn (string $l): string => __($l), Question::DIFFICULTIES))
                        ->default('medium'),
                    SelectField::make('category_id')->label(__('التصنيف'))->half()
                        ->options(Taxonomy::ofType('question_category')->get()
                            ->mapWithKeys(fn (Taxonomy $t): array => [$t->getKey() => (string) $t->name])->all()),
                    NumberField::make('marks')->label(__('الدرجة'))->range(0, 1000)->half()->default(1),
                    NumberField::make('negative_marks')->label(__('الخصم عند الخطأ'))->range(0, 1000)->half()->default(0),
                ]),

            Section::make(__('الخيارات والإجابة'))
                ->description(__('لأسئلة الاختيار والصواب والخطأ والقائمة المنسدلة.'))
                ->fields([
                    ChoicesField::make('options')->label(__('الخيارات'))->dependsOn('type'),
                ]),

            Section::make(__('ما يراه الطالب بعد التصحيح'))
                ->description(__('هنا يتعلّم فعلاً: الدرجة وحدها لا تُصلح خطأً.'))
                ->fields([
                    TranslatableField::make('steps')->label(__('خطوات الحل'))->long()
                        ->hint(__('سطر لكل خطوة، بالترتيب. المعادلات بين علامتَي $.')),
                    TranslatableField::make('explanation')->label(__('شرح الإجابة'))->long(),
                ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('بنك الأسئلة فارغ'),
            'body' => __('اجمع أسئلتك هنا مرة واحدة، ثم استخدمها في أي اختبار.'),
        ];
    }
}
