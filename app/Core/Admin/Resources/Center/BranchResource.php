<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Center;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BooleanColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\SwitchField;
use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Fields\TranslatableField;
use App\Core\Admin\Resource;
use App\Models\User;
use App\Modules\Center\Models\Branch;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class BranchResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::CENTER_MANAGE;
    }

    /** الفرع يستهلك حدّ «الفروع» في الباقة. */
    public function quotaKey(Request $request): ?string
    {
        return 'branches';
    }

    public function model(): string
    {
        return Branch::class;
    }

    public function label(): string
    {
        return __('الفروع');
    }

    public function singularLabel(): string
    {
        return __('فرع');
    }

    public function query(): Builder
    {
        return Branch::query()->withCount('rooms');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')->label(__('الفرع'))->searchable()->wrap()->description('code'),
            TextColumn::make('phone')->label(__('الهاتف'))->mono(),
            TextColumn::make('rooms_count')->label(__('القاعات'))->mono()->align('end'),
            BooleanColumn::make('is_active')->label(__('نشط')),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('الفرع'))->fields([
                TranslatableField::make('name')->label(__('الاسم'))->required(),
                TextField::make('code')->label(__('الكود'))->half()->rules(['max:16']),
                TextField::make('phone')->label(__('الهاتف'))->half(),
                TextField::make('whatsapp')->label(__('واتساب'))->half(),
                SelectField::make('manager_id')->label(__('المدير'))->half()
                    ->options(User::whereIn('role', ['owner', 'admin', 'staff'])->pluck('name', 'id')->all()),
                TextareaField::make('address')->label(__('العنوان')),
                SwitchField::make('is_active')->label(__('نشط'))->default(true),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return ['title' => __('لا فروع بعد'), 'body' => __('حتى السنتر بمكان واحد يحتاج فرعاً واحداً مسجّلاً.')];
    }
}
