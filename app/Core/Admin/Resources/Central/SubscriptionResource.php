<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Central;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Core\Billing\Models\Subscription;
use App\Core\Support\Money;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SubscriptionResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::BILLING_MANAGE;
    }

    public function model(): string
    {
        return Subscription::class;
    }

    public function label(): string
    {
        return __('الاشتراكات');
    }

    public function singularLabel(): string
    {
        return __('اشتراك');
    }

    public function layout(): string
    {
        return 'layouts.super-admin';
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(): Builder
    {
        return Subscription::query()->with(['tenant', 'plan']);
    }

    public function recordUrl(Model $record, string $key): ?string
    {
        return $record->tenant_id === null ? null : url('/admin/tenants/'.$record->tenant_id);
    }

    public function columns(): array
    {
        return [
            TextColumn::make('tenant_id')
                ->label(__('المشترك'))
                ->searchable()
                ->wrap()
                ->using(fn (?string $id, Subscription $s): string => $s->tenant?->name ?? (string) $id),

            TextColumn::make('plan_key')->label(__('الباقة'))->sortable()->searchable(),

            BadgeColumn::make('status')
                ->label(__('الحالة'))
                ->tones([
                    'trialing' => 'info', 'active' => 'success', 'past_due' => 'warning',
                    'paused' => 'neutral', 'cancelled' => 'neutral', 'expired' => 'danger',
                ])
                ->labels(array_map(fn (string $l): string => __($l), Subscription::STATUSES))
                ->sortable(),

            TextColumn::make('amount_minor')
                ->label(__('القيمة'))
                ->mono()
                ->align('end')
                ->sortable()
                ->using(fn (?int $minor, Subscription $s): string => Money::fromMinor((int) $minor, $s->currency)->format()
                    .' / '.($s->interval === 'year' ? __('سنة') : __('شهر'))),

            DateColumn::make('current_period_end')->label(__('التجديد القادم'))->sortable(),

            TextColumn::make('auto_renew')
                ->label(__('التجديد التلقائي'))
                ->using(fn ($v): string => $v ? __('مفعّل') : __('موقوف')),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), Subscription::STATUSES)),

            SelectFilter::make('interval')->label(__('الدورة'))
                ->options(['month' => __('شهري'), 'year' => __('سنوي')]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا اشتراكات بعد'),
            'body' => __('كل من يختار باقة سيظهر هنا باشتراكه وقيمته وموعد تجديده.'),
        ];
    }
}
