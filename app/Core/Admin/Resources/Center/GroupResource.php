<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Center;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\DateField;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Models\User;
use App\Modules\Center\Models\Branch;
use App\Modules\Center\Models\Grade;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Subject;
use App\Modules\Center\Models\SubjectTeacher;
use App\Modules\Center\Models\Term;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class GroupResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::CENTER_MANAGE;
    }

    /** المجموعة تستهلك حدّ «المجموعات» في الباقة. */
    public function quotaKey(Request $request): ?string
    {
        return 'groups';
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

    /**
     * صفّ المجموعة يفتح مواعيدها.
     *
     * كان يشير إلى `/admin/groups/{id}` — ولا مسار بهذا الاسم، فكل
     * صفّ في الشاشة رابطٌ مكسور. وأول ما تريده الإدارة من مجموعة هو
     * موعدها وقاعتها، فهذه وجهته الصحيحة.
     */
    public function nextStep(Model $record, string $key): ?array
    {
        return [
            'url' => url('/admin/groups/'.$record->getKey().'/slots'),
            'label' => __('مواعيد المجموعة'),
            'hint' => __('أضف مواعيدها الأسبوعية ثم ولّد حصص الترم.'),
        ];
    }

    public function recordUrl(Model $record, string $key): ?string
    {
        return url('/admin/groups/'.$record->getKey().'/slots');
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

            TextColumn::make('venue')->label(__('المكان'))
                ->using(fn ($v, Group $g): string => $g->venueLabel()),

            BadgeColumn::make('kind')->label(__('الشكل'))->sortable()
                ->tones(['private' => 'accent', 'group' => 'neutral'])
                ->labels(array_map(fn (string $l): string => __($l), Group::KINDS)),

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

            SelectFilter::make('venue')->label(__('المكان'))
                ->options(array_map(fn (string $l): string => __($l), Group::VENUES)),

            SelectFilter::make('kind')->label(__('الشكل'))
                ->options(array_map(fn (string $l): string => __($l), Group::KINDS)),

            ...(module_enabled('center-premises') ? [
                SelectFilter::make('branch_id')->label(__('الفرع'))
                    ->options(Branch::get()->mapWithKeys(fn (Branch $b): array => [$b->getKey() => (string) $b->name])->all()),
            ] : []),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('المجموعة'))->fields([
                TranslatableField::make('name')->label(__('الاسم'))->required()
                    ->hint(__('اسم يعرفه الطلاب: «فيزياء ٣ث — سبت ٤م».')),
                SelectField::make('kind')->label(__('الشكل'))->half()
                    ->options(array_map(fn (string $l): string => __($l), Group::KINDS))
                    ->default('group')
                    ->hint(__('الفردي سعته طالب واحد.')),
                SelectField::make('venue')->label(__('المكان'))->half()
                    ->options(array_map(fn (string $l): string => __($l), Group::VENUES))
                    ->default(module_enabled('center-premises') ? 'branch' : 'online'),
                TextField::make('location')->label(__('اسم المكان أو عنوانه'))->half()
                    ->hint(__('للسنتر اكتب اسمه، وللبيت عنوانه — هذا ما يقرأه ولي الأمر.')),
                TextField::make('meeting_url')->label(__('رابط الحصة'))->url()->half()
                    ->hint(__('للأونلاين — يصل الطالب برابطه.')),
                // الفرع لمن يملك فروعاً: المدرّس المستقل لا يرى الحقل أصلاً
                ...(module_enabled('center-premises') ? [
                    SelectField::make('branch_id')->label(__('الفرع'))->half()
                        ->options(Branch::get()->mapWithKeys(fn (Branch $b): array => [$b->getKey() => (string) $b->name])->all())
                        ->hint(__('لمجموعات فروعك أنت وحدها — اتركه فارغاً لما سواها.')),
                ] : []),
                SelectField::make('subject_id')->label(__('المادة'))->half()
                    ->options(Subject::get()->mapWithKeys(fn (Subject $s): array => [$s->getKey() => (string) $s->name])->all()),
                // بترتيب المرحلة ثم الصف — لا بترتيب الإدخال، وإلا جاء «أول ابتدائي» آخر القائمة
                SelectField::make('grade_id')->label(__('الصف'))->half()
                    ->options(Grade::with('stage')->get()
                        ->sortBy(fn (Grade $g): array => [(int) ($g->stage?->position ?? 0), (int) $g->position])
                        ->mapWithKeys(fn (Grade $g): array => [$g->getKey() => trim(($g->stage?->name ?? '').' — '.$g->name, ' —')])->all()),
                /*
                 | المدرّس يُعرَض بمادته لا مجرّداً.
                 |
                 | كانت القائمة تعرض **كل** مستخدم بدور إداري، فيُسنَد
                 | صفّ الكيمياء إلى مدرّس اللغة العربية بضغطة سهو —
                 | ولا شيء في النظام يعترض.
                 */
                /*
                 | المدرّس المستقل هو مدرّس كل مجموعاته: سؤاله «من المدرّس؟»
                 | في كل مرّة عبث، فيُخفى الحقل ويُملأ باسمه في fillable().
                 */
                ...(module_enabled('center-staff') ? [
                    SelectField::make('teacher_id')->label(__('المدرّس'))->half()
                        ->options(self::teacherOptions()),
                ] : []),
                // الترم يُعرض حين يكون هناك ترمات أصلاً — قائمة فارغة سؤال بلا جواب
                ...(Term::query()->exists() ? [
                    SelectField::make('term_id')->label(__('الترم'))->half()
                        ->options(Term::get()->mapWithKeys(fn (Term $t): array => [$t->getKey() => (string) $t->name])->all()),
                ] : []),
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
                DateField::make('start_date')->label(__('تاريخ البدء'))->half(),
                DateField::make('end_date')->label(__('تاريخ الانتهاء'))->half()->rules(['after_or_equal:start_date']),
            ]),
        ];
    }

    /**
     * حين لا يُعرض حقل المدرّس، المجموعة لمن ينشئها.
     *
     * بغير هذا تُحفظ المجموعة بلا مدرّس، فلا يكتشف المحرّك تزامن
     * حصّتين للمدرّسة نفسها، ولا يظهر اسمها في جدولها.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function fillable(array $input, string $context): array
    {
        $data = parent::fillable($input, $context);

        if ($context === 'create' && ! module_enabled('center-staff') && ! isset($data['teacher_id'])) {
            $data['teacher_id'] = auth()->id();
        }

        return $data;
    }

    /**
     * المدرّسون بموادّهم — ومن لا مادة له في الذيل.
     *
     * @return array<int, string>
     */
    private static function teacherOptions(): array
    {
        $subjects = SubjectTeacher::active()->with(['teacher', 'subject'])->get()
            ->groupBy('user_id')
            ->map(fn ($rows): string => $rows->map(fn ($row) => (string) $row->subject?->name)->filter()->unique()->implode(' · '));

        return User::whereIn('role', ['instructor', 'owner', 'admin'])
            ->orderBy('name')->get()
            ->sortBy(fn (User $user): int => $subjects->has($user->getKey()) ? 0 : 1)
            ->mapWithKeys(fn (User $user): array => [
                $user->getKey() => filled($subjects[$user->getKey()] ?? null)
                    ? $user->name.' — '.$subjects[$user->getKey()]
                    : $user->name,
            ])->all();
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا مجموعات بعد'),
            'body' => __('المجموعة هي وحدة السنتر: مادة ومدرّس وموعد وطلاب.'),
        ];
    }
}
