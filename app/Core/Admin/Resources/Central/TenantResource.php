<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Central;

use App\Core\Access\Ability;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * المشتركون كما نراهم نحن: عملاء في طبقة الـ SaaS.
 */
final class TenantResource extends Resource
{
    public const STATUSES = [
        'provisioning' => 'قيد التجهيز',
        'trialing' => 'تجربة مجانية',
        'active' => 'نشط',
        'past_due' => 'متعثّر في السداد',
        'suspended' => 'معلَّق',
        'cancelled' => 'ملغى',
        'archived' => 'مؤرشف',
    ];

    public const MODES = [
        'solo' => 'مدرّس فردي',
        'marketplace' => 'متعدد المدرّسين',
        'center' => 'سنتر تعليمي',
        'hybrid' => 'شامل',
    ];

    public function viewAbility(): string
    {
        return Ability::USERS_MANAGE;
    }

    public function model(): string
    {
        return Tenant::class;
    }

    public function label(): string
    {
        return __('المشتركون');
    }

    public function singularLabel(): string
    {
        return __('مشترك');
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
        return Tenant::query()->with('domains');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('المشترك'))
                ->description('owner_email')
                ->sortable()
                ->searchable()
                ->wrap(),

            TextColumn::make('slug')
                ->label(__('النطاق'))
                ->mono()
                ->searchable()
                ->using(fn (?string $slug, Tenant $t): string => $t->domains->firstWhere('is_primary', true)?->domain ?? (string) $slug),

            BadgeColumn::make('status')
                ->label(__('الحالة'))
                ->tones([
                    'provisioning' => 'neutral',
                    'trialing' => 'info',
                    'active' => 'success',
                    'past_due' => 'warning',
                    'suspended' => 'danger',
                    'cancelled' => 'neutral',
                    'archived' => 'neutral',
                ])
                ->labels(array_map(fn (string $l): string => __($l), self::STATUSES))
                ->sortable(),

            BadgeColumn::make('platform_mode')
                ->label(__('النمط'))
                ->tones(['center' => 'accent', 'hybrid' => 'primary'])
                ->labels(array_map(fn (string $l): string => __($l), self::MODES))
                ->sortable(),

            TextColumn::make('plan_key')
                ->label(__('الباقة'))
                ->sortable(),

            TextColumn::make('country')
                ->label(__('الدولة'))
                ->mono()
                ->using(fn (?string $c, Tenant $t): string => trim(($c ?? '').' · '.($t->currency ?? ''), ' ·')),

            DateColumn::make('created_at')
                ->label(__('تاريخ الاشتراك'))
                ->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), self::STATUSES)),

            SelectFilter::make('platform_mode')
                ->label(__('النمط'))
                ->options(array_map(fn (string $l): string => __($l), self::MODES)),

            SelectFilter::make('plan_key')
                ->label(__('الباقة'))
                ->options(['starter' => __('البداية'), 'growth' => __('النمو'),
                    'professional' => __('الاحترافية'), 'center' => __('السنتر')]),
        ];
    }

    /** المشترك له ملف كامل لا شاشة تحرير — ولا يُعدَّل بحقول مباشرة. */
    public function recordUrl(Model $record, string $key): ?string
    {
        return url('/admin/tenants/'.$record->getKey());
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا يوجد مشتركون بعد'),
            'body' => __('سيظهر هنا كل من ينشئ منصّته، مع حالته وباقته واستهلاكه.'),
        ];
    }
}
