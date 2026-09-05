<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Center;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Center\Models\Branch;
use App\Modules\Center\Models\Grade;
use App\Modules\Center\Models\Student;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class CenterStudentResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::CENTER_VIEW;
    }

    public function manageAbility(): string
    {
        return Ability::CENTER_MANAGE;
    }

    /** ملفّ الطالب يستهلك حدّ «الطلبة» — وهو الحدّ الذي يُقاس به سعر الباقة. */
    public function quotaKey(Request $request): ?string
    {
        return 'students';
    }

    public function model(): string
    {
        return Student::class;
    }

    public function label(): string
    {
        return __('طلاب السنتر');
    }

    public function singularLabel(): string
    {
        return __('طالب');
    }

    public function query(): Builder
    {
        return Student::query()->with(['user', 'grade', 'branch']);
    }

    public function recordUrl(Model $record, string $key): ?string
    {
        return url('/admin/center-students/'.$record->getKey());
    }

    /**
     * الإنشاء له شاشة خاصة (حساب + سجلّ + ولي أمر معاً)، لا نموذج
     * المورد العام — فالزرّ يظهر ويقود إليها.
     */
    public function canCreate(): bool
    {
        return true;
    }

    public function searchableColumns(): array
    {
        return ['code'];
    }

    public function columns(): array
    {
        return [
            TextColumn::make('code')->label(__('الكود'))->mono()->searchable()->sortable(),

            TextColumn::make('user_id')->label(__('الاسم'))->wrap()
                ->using(fn ($v, Student $s): string => $s->name()),

            TextColumn::make('grade_id')->label(__('الصف'))
                ->using(fn ($v, Student $s): string => (string) ($s->grade?->name ?? '—')),

            TextColumn::make('school')->label(__('المدرسة'))->wrap(),

            TextColumn::make('branch_id')->label(__('الفرع'))
                ->using(fn ($v, Student $s): string => (string) ($s->branch?->name ?? '—')),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['active' => 'success', 'paused' => 'warning', 'left' => 'neutral'])
                ->labels(['active' => __('نشط'), 'paused' => __('موقوف'), 'left' => __('غادر')]),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(['active' => __('نشط'), 'paused' => __('موقوف'), 'left' => __('غادر')]),

            SelectFilter::make('grade_id')->label(__('الصف'))
                ->options(Grade::get()->mapWithKeys(fn (Grade $g): array => [$g->getKey() => (string) $g->name])->all()),

            SelectFilter::make('branch_id')->label(__('الفرع'))
                ->options(Branch::get()->mapWithKeys(fn (Branch $b): array => [$b->getKey() => (string) $b->name])->all()),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا طلاب بعد'),
            'body' => __('كل طالب يحصل على كود كارنيه يُمسح عند الباب.'),
        ];
    }
}
