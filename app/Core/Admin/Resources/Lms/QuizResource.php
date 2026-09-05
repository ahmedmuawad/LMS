<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Lms;

use App\Core\Access\Ability;
use App\Core\Access\Scope;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\QuestionPoolField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Models\User;
use App\Modules\Lms\Models\Quiz;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class QuizResource extends Resource
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
        return Quiz::class;
    }

    public function label(): string
    {
        return __('الاختبارات');
    }

    public function singularLabel(): string
    {
        return __('اختبار');
    }

    public function query(): Builder
    {
        return Quiz::query()->withCount('questions');
    }

    public function recordUrl(Model $record, string $key): ?string
    {
        return url('/admin/quizzes/'.$record->getKey().'/questions');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('title')->label(__('الاختبار'))->searchable()->wrap()->sortable(),

            BadgeColumn::make('type')->label(__('النوع'))
                ->tones(['dynamic' => 'accent'])
                ->labels(['static' => __('ثابت'), 'dynamic' => __('عشوائي من بنك الأسئلة')]),

            NumberColumn::make('questions_count')->label(__('الأسئلة')),

            TextColumn::make('time_limit_minutes')->label(__('الوقت'))->mono()->align('end')
                ->using(fn ($v): string => (int) $v === 0 ? __('بلا وقت') : $v.' '.__('دقيقة')),

            TextColumn::make('max_attempts')->label(__('المحاولات'))->mono()->align('end')
                ->using(fn ($v): string => (int) $v === 0 ? __('بلا حد') : (string) $v),

            NumberColumn::make('passing_percentage')->label(__('النجاح %')),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('type')->label(__('النوع'))
                ->options(['static' => __('ثابت'), 'dynamic' => __('عشوائي')]),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('الاختبار'))->fields([
                TranslatableField::make('title')->label(__('العنوان'))->required(),
                TranslatableField::make('description')->label(__('الوصف'))->long(),
                SelectField::make('type')->label(__('النوع'))->half()
                    ->options(['static' => __('ثابت — أسئلة محدّدة'), 'dynamic' => __('عشوائي من بنك الأسئلة')])
                    ->default('static'),
                NumberField::make('questions_count')->label(__('عدد الأسئلة العشوائية'))->range(1, 200)->half()
                    ->hint(__('للاختبار العشوائي بلا خلطة صعوبات.')),
            ]),

            Section::make(__('خلطة الصعوبات'))
                ->description(__('للاختبار العشوائي: كم سؤالاً من كل مستوى. حدّدها فتسبق «عدد الأسئلة» أعلاه.'))
                ->fields([
                    QuestionPoolField::make('question_pool')->label(__('من بنك الأسئلة'))
                        ->categories(Taxonomy::ofType('question_category')->get()
                            ->mapWithKeys(fn (Taxonomy $t): array => [$t->getKey() => (string) $t->name])->all()),
                ]),

            Section::make(__('القواعد'))->fields([
                NumberField::make('time_limit_minutes')->label(__('الزمن'))->suffix(__('دقيقة'))
                    ->range(0, 600)->half()->default(30)->hint(__('صفر يعني بلا وقت.')),
                NumberField::make('max_attempts')->label(__('المحاولات'))->range(0, 50)->half()->default(3)
                    ->hint(__('صفر يعني بلا حد.')),
                NumberField::make('passing_percentage')->label(__('نسبة النجاح'))->suffix('%')
                    ->range(0, 100)->half()->default(60),
                NumberField::make('retake_delay_hours')->label(__('انتظار بين المحاولات'))->suffix(__('ساعة'))
                    ->range(0, 720)->half()->default(0),
                SwitchField::make('shuffle_questions')->label(__('خلط الأسئلة'))->default(true),
                SwitchField::make('shuffle_answers')->label(__('خلط الإجابات'))->default(true),

                /*
                 | المراقبة تُعلَن للطالب قبل أن تبدأ.
                 |
                 | مراقبةٌ خفيّة تُشعره بالخديعة حين يكتشفها، والمعلَنة
                 | تردعه قبل أن يحاول — والردع هو الغرض لا الإيقاع.
                 */
                SwitchField::make('proctored')->label(__('مراقبة الامتحان'))
                    ->hint(__('يُسجَّل خروج الطالب من النافذة ونسخه ولصقه، ويرى المدرّس التقرير مع ورقته.')),

                NumberField::make('max_violations')->label(__('إنهاء تلقائي بعد'))->half()
                    ->suffix(__('مخالفة'))->range(0, 20)->default(0)
                    ->hint(__('صفر يعني: سجّل ولا تُنهِ. وإشعارٌ يقفز على الهاتف يُخرج الطالب بلا إرادته، فالإنهاء بعد واحدة ظلمٌ يقع كثيراً.')),
                SwitchField::make('negative_marking')->label(__('درجات سالبة للخطأ'))
                    ->hint(__('يردع التخمين ويُحبط المتردّد — فعّله بوعي.')),
                SelectField::make('show_answers')->label(__('إظهار الإجابات الصحيحة'))->half()
                    ->options([
                        'never' => __('أبداً'),
                        'after_submit' => __('بعد التسليم'),
                        'after_pass' => __('بعد النجاح فقط'),
                    ])->default('after_pass'),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return ['title' => __('لا اختبارات بعد'), 'body' => __('الاختبار يقيس ما تعلّمه الطالب فعلاً، ويفتح شهادته.')];
    }
}
