<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Commerce;

use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Commerce\Models\Order;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OrderResource extends Resource
{
    public function model(): string
    {
        return Order::class;
    }

    public function label(): string
    {
        return __('الطلبات');
    }

    public function singularLabel(): string
    {
        return __('طلب');
    }

    public function defaultSort(): array
    {
        return ['placed_at', 'desc'];
    }

    public function query(): Builder
    {
        return Order::query()->with(['user', 'items']);
    }

    public function recordUrl(Model $record, string $key): ?string
    {
        return url('/admin/orders/'.$record->getKey());
    }

    public function columns(): array
    {
        return [
            TextColumn::make('number')->label(__('الرقم'))->mono()->sortable()->searchable(),

            TextColumn::make('user_id')->label(__('العميل'))->wrap()
                ->using(fn ($v, Order $o): string => $o->customerName())
                ->description('guest_email'),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones([
                    'pending' => 'neutral', 'awaiting_payment' => 'warning', 'paid' => 'success',
                    'processing' => 'info', 'completed' => 'success', 'cancelled' => 'neutral',
                    'refunded' => 'warning', 'failed' => 'danger',
                ])
                ->labels(array_map(fn (string $l): string => __($l), Order::STATUSES)),

            TextColumn::make('total_minor')->label(__('الإجمالي'))->mono()->align('end')->sortable()
                ->using(fn ($v, Order $o): string => $o->total()->format()),

            TextColumn::make('gateway')->label(__('الوسيلة'))
                ->using(fn (?string $v): string => $v === null ? '—' : __(config('payments.labels.'.$v, $v))),

            DateColumn::make('placed_at')->label(__('تاريخ الطلب'))->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), Order::STATUSES)),

            SelectFilter::make('gateway')->label(__('وسيلة الدفع'))
                ->options(collect(config('payments.gateways', []))
                    ->mapWithKeys(fn (array $g): array => [$g['key'] => __($g['label'])])->all()),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا طلبات بعد'),
            'body' => __('كل عملية شراء ستظهر هنا بحالتها ودفعاتها.'),
        ];
    }
}
