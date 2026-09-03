<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Commerce;

use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Commerce\Models\Refund;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class RefundResource extends Resource
{
    public function model(): string
    {
        return Refund::class;
    }

    public function label(): string
    {
        return __('طلبات الاسترداد');
    }

    public function singularLabel(): string
    {
        return __('طلب استرداد');
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(): Builder
    {
        return Refund::query()->with(['order.user', 'requester']);
    }

    public function recordUrl(Model $record, string $key): ?string
    {
        return $record->order_id === null ? null : url('/admin/orders/'.$record->order_id);
    }

    public function columns(): array
    {
        return [
            TextColumn::make('order_id')->label(__('الطلب'))->mono()
                ->using(fn ($v, Refund $r): string => (string) ($r->order?->number ?? '—')),

            TextColumn::make('requested_by')->label(__('العميل'))->wrap()
                ->using(fn ($v, Refund $r): string => (string) ($r->order?->customerName() ?? '—')),

            TextColumn::make('amount_minor')->label(__('المبلغ'))->mono()->align('end')
                ->using(fn ($v, Refund $r): string => $r->amount()->format()),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['requested' => 'warning', 'approved' => 'info', 'processed' => 'success', 'rejected' => 'neutral'])
                ->labels(array_map(fn (string $l): string => __($l), Refund::STATUSES)),

            TextColumn::make('reason')->label(__('السبب'))->wrap(),

            DateColumn::make('created_at')->label(__('تاريخ الطلب'))->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), Refund::STATUSES)),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا طلبات استرداد'),
            'body' => __('وهذه علامة جيدة — يعني أن ما تبيعه يطابق ما تعد به.'),
        ];
    }
}
