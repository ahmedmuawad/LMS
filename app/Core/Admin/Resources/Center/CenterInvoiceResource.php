<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Center;

use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Modules\Center\Models\Invoice;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CenterInvoiceResource extends Resource
{
    public function model(): string
    {
        return Invoice::class;
    }

    public function label(): string
    {
        return __('فواتير السنتر');
    }

    public function singularLabel(): string
    {
        return __('فاتورة');
    }

    public function defaultSort(): array
    {
        return ['due_on', 'desc'];
    }

    public function query(): Builder
    {
        return Invoice::query()->with(['student.user', 'group']);
    }

    public function recordUrl(Model $record, string $key): ?string
    {
        return url('/admin/center-students/'.$record->student_id);
    }

    public function columns(): array
    {
        return [
            TextColumn::make('number')->label(__('الرقم'))->mono()->searchable()->sortable(),

            TextColumn::make('student_id')->label(__('الطالب'))->wrap()
                ->using(fn ($v, Invoice $i): string => (string) ($i->student?->name() ?? '—')),

            TextColumn::make('group_id')->label(__('المجموعة'))->wrap()
                ->using(fn ($v, Invoice $i): string => (string) ($i->group?->name ?? '—')),

            TextColumn::make('period')->label(__('الفترة'))->mono()->sortable(),

            TextColumn::make('total_minor')->label(__('الإجمالي'))->mono()->align('end')
                ->using(fn ($v, Invoice $i): string => $i->total()->format()),

            TextColumn::make('paid_minor')->label(__('المتبقّي'))->mono()->align('end')
                ->using(fn ($v, Invoice $i): string => $i->remaining()->format()),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones(['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger',
                    'draft' => 'neutral', 'void' => 'neutral'])
                ->labels(array_map(fn (string $l): string => __($l), Invoice::STATUSES)),

            DateColumn::make('due_on')->label(__('الاستحقاق'))->sortable(),
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
            'body' => __('أصدر فواتير الفترة من صفحة المجموعة.'),
        ];
    }
}
