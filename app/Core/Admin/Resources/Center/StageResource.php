<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Center;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\NumberColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\NumberField;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Resource;
use App\Modules\Center\Models\Stage;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * المراحل: ابتدائي · إعدادي · ثانوي.
 *
 * لم يكن لها شاشة، فكانت تُزرع من الأوامر وحدها — ومدرّسة تفتح
 * أكاديميتها لأول مرة تجد نموذج المجموعة يطلب صفّاً لا سبيل إلى إنشائه.
 */
final class StageResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::CENTER_MANAGE;
    }

    public function model(): string
    {
        return Stage::class;
    }

    public function label(): string
    {
        return __('المراحل');
    }

    public function singularLabel(): string
    {
        return __('مرحلة');
    }

    public function query(): Builder
    {
        return Stage::query()->withCount('grades')->orderBy('position');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('المرحلة'))->searchable()->wrap(),
            NumberColumn::make('grades_count')->label(__('الصفوف')),
            NumberColumn::make('position')->label(__('الترتيب'))->sortable(),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('المرحلة'))->fields([
                TranslatableField::make('name')->label(__('الاسم'))->required()
                    ->hint(__('مثل: ابتدائي · إعدادي · ثانوي.')),
                NumberField::make('position')->label(__('الترتيب'))->range(0, 99)->half()->default(0)
                    ->hint(__('الأصغر أولاً في القوائم.')),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا مراحل بعد'),
            'body' => __('ابدأ بالمراحل التي تُدرّس لها، ثم أضف صفوف كل مرحلة.'),
        ];
    }
}
