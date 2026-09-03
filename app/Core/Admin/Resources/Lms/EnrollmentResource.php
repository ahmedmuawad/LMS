<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Lms;

use App\Core\Access\Ability;
use App\Core\Access\Scope;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Models\User;
use App\Modules\Lms\Models\Enrollment;
use Illuminate\Contracts\Database\Eloquent\Builder;

final class EnrollmentResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::ENROLLMENTS_VIEW;
    }

    public function manageAbility(): string
    {
        return Ability::ENROLLMENTS_MANAGE;
    }

    public function scopeFor(Builder $query, ?User $user): Builder
    {
        return app(Scope::class)->byCourse($query, $user);
    }

    public function model(): string
    {
        return Enrollment::class;
    }

    public function label(): string
    {
        return __('التسجيلات');
    }

    public function singularLabel(): string
    {
        return __('تسجيل');
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(): Builder
    {
        return Enrollment::query()->with(['user', 'course']);
    }

    public function columns(): array
    {
        return [
            TextColumn::make('user_id')->label(__('الطالب'))->wrap()
                ->using(fn ($v, Enrollment $e): string => (string) ($e->user?->name ?? '—')),

            TextColumn::make('course_id')->label(__('الكورس'))->wrap()
                ->using(fn ($v, Enrollment $e): string => (string) ($e->course?->title ?? '—')),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['active' => 'success', 'completed' => 'primary', 'expired' => 'warning',
                    'suspended' => 'danger', 'refunded' => 'neutral'])
                ->labels(array_map(fn (string $l): string => __($l), Enrollment::STATUSES)),

            TextColumn::make('progress_percent')->label(__('التقدّم'))->mono()->align('end')->sortable()
                ->using(fn ($v): string => $v.'%'),

            BadgeColumn::make('source')->label(__('المصدر'))
                ->labels(array_map(fn (string $l): string => __($l), Enrollment::SOURCES)),

            DateColumn::make('expires_at')->label(__('ينتهي في'))->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), Enrollment::STATUSES)),

            SelectFilter::make('source')->label(__('المصدر'))
                ->options(array_map(fn (string $l): string => __($l), Enrollment::SOURCES)),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا تسجيلات بعد'),
            'body' => __('كل من يشتري أو يُضاف يدوياً أو يستخدم كوداً سيظهر هنا.'),
        ];
    }
}
