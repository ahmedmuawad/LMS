<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Lms;

use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Resource;
use App\Modules\Lms\Models\Certificate;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CertificateResource extends Resource
{
    public function model(): string
    {
        return Certificate::class;
    }

    public function label(): string
    {
        return __('الشهادات');
    }

    public function singularLabel(): string
    {
        return __('شهادة');
    }

    public function defaultSort(): array
    {
        return ['issued_at', 'desc'];
    }

    public function query(): Builder
    {
        return Certificate::query()->with(['user', 'course']);
    }

    public function recordUrl(Model $record, string $key): ?string
    {
        return url('/certificate/'.$record->code);
    }

    public function columns(): array
    {
        return [
            TextColumn::make('code')->label(__('الكود'))->mono()->searchable()->sortable(),

            TextColumn::make('user_id')->label(__('الطالب'))->wrap()
                ->using(fn ($v, Certificate $c): string => (string) ($c->user?->name ?? '—')),

            TextColumn::make('course_id')->label(__('الكورس'))->wrap()
                ->using(fn ($v, Certificate $c): string => (string) ($c->course?->title ?? '—')),

            BadgeColumn::make('revoked_at')->label(__('الحالة'))
                ->using(fn ($v, Certificate $c): string => $c->statusLabel()),

            DateColumn::make('issued_at')->label(__('تاريخ الإصدار'))->sortable(),
            DateColumn::make('expires_at')->label(__('تنتهي في'))->sortable(),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا شهادات بعد'),
            'body' => __('تُصدَر تلقائياً لمن يُكمل كورساً، ولكل شهادة صفحة تحقّق عامة بكودها.'),
        ];
    }
}
