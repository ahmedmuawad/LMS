<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Commerce;

use App\Core\Access\Ability;
use App\Core\Access\Scope;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Models\User;
use App\Modules\Commerce\Models\Payout;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class PayoutResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::PAYOUTS_VIEW;
    }

    public function manageAbility(): string
    {
        return Ability::PAYOUTS_MANAGE;
    }

    public function scopeFor(Builder $query, ?User $user): Builder
    {
        return app(Scope::class)->byInstructor($query, $user);
    }

    public function model(): string
    {
        return Payout::class;
    }

    public function label(): string
    {
        return __('تحويلات المدرّسين');
    }

    public function singularLabel(): string
    {
        return __('تحويل');
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(): Builder
    {
        return Payout::query()->with('instructor.user');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('reference')->label(__('المرجع'))->mono()->searchable()->sortable(),

            TextColumn::make('instructor_id')->label(__('المدرّس'))->wrap()
                ->using(fn ($v, Payout $p): string => (string) ($p->instructor?->name() ?? '—')),

            TextColumn::make('amount_minor')->label(__('المبلغ'))->mono()->align('end')->sortable()
                ->using(fn ($v, Payout $p): string => $p->amount()->format()),

            TextColumn::make('method')->label(__('الوسيلة'))
                ->using(fn (?string $v): string => __(Payout::METHODS[$v] ?? ($v ?? '—'))),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['pending' => 'warning', 'processing' => 'info', 'paid' => 'success', 'failed' => 'danger'])
                ->labels(array_map(fn (string $l): string => __($l), Payout::STATUSES)),

            DateColumn::make('paid_at')->label(__('تاريخ التحويل'))->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), Payout::STATUSES)),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا تحويلات بعد'),
            'body' => __('مستحقات المدرّسين تنضج بعد انقضاء مهلة الاسترداد، ثم تُجمَّع هنا في تحويل واحد.'),
        ];
    }
}
