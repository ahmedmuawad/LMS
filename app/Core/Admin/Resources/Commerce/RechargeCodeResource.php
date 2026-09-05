<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Commerce;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Commerce\Models\RechargeCode;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class RechargeCodeResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::CODES_MANAGE;
    }

    public function feature(): ?string
    {
        return 'recharge_codes';
    }

    public function model(): string
    {
        return RechargeCode::class;
    }

    public function label(): string
    {
        return __('أكواد الشحن');
    }

    public function singularLabel(): string
    {
        return __('كود شحن');
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(): Builder
    {
        return RechargeCode::query()->with(['user', 'batch']);
    }

    public function columns(): array
    {
        return [
            TextColumn::make('code')->label(__('الكود'))->mono()->searchable(),

            BadgeColumn::make('type')->label(__('النوع'))
                ->labels(array_map(fn (string $l): string => __($l), RechargeCode::TYPES)),

            TextColumn::make('value_minor')->label(__('القيمة'))->mono()->align('end')
                ->using(fn ($v, RechargeCode $c): string => $c->type === 'wallet' ? $c->value()->format() : '—'),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['unused' => 'success', 'used' => 'neutral', 'void' => 'danger', 'expired' => 'warning'])
                ->labels(array_map(fn (string $l): string => __($l), RechargeCode::STATUSES)),

            TextColumn::make('used_by')->label(__('استخدمه'))
                ->using(fn ($v, RechargeCode $c): string => (string) ($c->user?->name ?? '—')),

            DateColumn::make('expires_at')->label(__('ينتهي في'))->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), RechargeCode::STATUSES)),

            SelectFilter::make('type')->label(__('النوع'))
                ->options(array_map(fn (string $l): string => __($l), RechargeCode::TYPES)),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا أكواد بعد'),
            'body' => __('ولّد دفعة أكواد واطبعها كروتاً — أهم وسيلة دفع لمن لا يملك بطاقة.'),
        ];
    }
}
