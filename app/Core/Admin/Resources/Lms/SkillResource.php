<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Lms;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Resource;
use App\Modules\Lms\Models\Skill;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class SkillResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::QUIZZES_MANAGE;
    }

    public function model(): string
    {
        return Skill::class;
    }

    public function label(): string
    {
        return __('المهارات');
    }

    public function singularLabel(): string
    {
        return __('مهارة');
    }

    public function query(): Builder
    {
        return Skill::query()->withCount('questions');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('المهارة'))->searchable()->wrap(),

            NumberColumn::make('questions_count')->label(__('أسئلتها'))->sortable(),

            TextColumn::make('mastery_percent')->label(__('حدّ الإتقان'))->mono()->align('end')
                ->using(fn ($v): string => $v.'%'),

            BooleanColumn::make('is_active')->label(__('مفعّلة')),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('المهارة'))
                ->description(__('المهارة تُقاس بأسئلة البنك: تَسِمُ السؤال بمهارته، فيُحسب إتقان الطالب من إجاباته — لا من تقديرٍ يدوي.'))
                ->fields([
                    TranslatableField::make('name')->label(__('الاسم'))->required(),
                    TextField::make('slug')->label(__('الرابط'))->required()->half()
                        ->rules(['alpha_dash', 'max:120', 'unique:skills,slug']),
                    TranslatableField::make('description')->label(__('الوصف'))->long(),
                    NumberField::make('mastery_percent')->label(__('حدّ الإتقان'))->suffix('%')
                        ->range(30, 100)->half()->default(70)
                        ->hint(__('النسبة التي تُعدّ إتقاناً. أقلّ منها يظهر للطالب موضعَ ضعف.')),
                    SwitchField::make('is_active')->label(__('مفعّلة'))->default(true),
                ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا مهارات بعد'),
            'body' => __('الدرجة تقول «٦٠٪» ولا تقول أين الضعف. والمهارة تقول: «الجبر ٩٠٪ والهندسة ٤٠٪» — فيعرف الطالب ما يراجع، وتعرف أنت ما تعيد شرحه للصفّ.'),
        ];
    }
}
