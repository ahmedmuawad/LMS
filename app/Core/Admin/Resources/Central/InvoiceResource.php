<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Central;

use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Core\Billing\Models\Invoice;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class InvoiceResource extends Resource
{
    public function model(): string
    {
        return Invoice::class;
    }

    public function label(): string
    {
        return __('الفواتير');
    }

    public function singularLabel(): string
    {
        return __('فاتورة');
    }

    public function layout(): string
    {
        return 'layouts.super-admin';
    }

    public function defaultSort(): array
    {
        return ['issued_at', 'desc'];
    }

    public function query(): Builder
    {
        return Invoice::query()->with('tenant');
    }

    public function recordUrl(Model $record, string $key): ?string
    {
        return url('/admin/invoices/'.$record->getKey());
    }

    public function columns(): array
    {
        return [
            TextColumn::make('number')->label(__('الرقم'))->mono()->sortable()->searchable(),

            TextColumn::make('tenant_id')
                ->label(__('المشترك'))
                ->wrap()
                ->using(fn (?string $id, Invoice $i): string => $i->tenant?->name ?? (string) $id),

            BadgeColumn::make('status')
                ->label(__('الحالة'))
                ->tones([
                    'draft' => 'neutral', 'open' => 'info', 'paid' => 'success',
                    'overdue' => 'danger', 'void' => 'neutral', 'refunded' => 'warning',
                ])
                ->labels(array_map(fn (string $l): string => __($l), Invoice::STATUSES))
                ->sortable(),

            TextColumn::make('total_minor')
                ->label(__('الإجمالي'))
                ->mono()
                ->align('end')
                ->sortable()
                ->using(fn (?int $m, Invoice $i): string => $i->total()->format()),

            DateColumn::make('issued_at')->label(__('تاريخ الإصدار'))->sortable(),
            DateColumn::make('due_at')->label(__('تاريخ الاستحقاق'))->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), Invoice::STATUSES)),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا فواتير بعد'),
            'body' => __('تُصدَر الفاتورة تلقائياً مع كل دورة اشتراك.'),
        ];
    }
}
