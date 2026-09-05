<?php

declare(strict_types=1);

namespace App\Core\Admin\Resources\Services;

use App\Core\Access\Ability;
use App\Core\Access\Roles;
use App\Core\Admin\Columns\BadgeColumn;
use App\Core\Admin\Columns\DateColumn;
use App\Core\Admin\Columns\TextColumn;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Fields\SelectField;
use App\Core\Admin\Fields\TextareaField;
use App\Core\Admin\Fields\TextField;
use App\Core\Admin\Filters\SelectFilter;
use App\Core\Admin\Resource;
use App\Models\User;
use App\Modules\Services\Models\Booking;
use App\Modules\Services\Models\Service;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class BookingResource extends Resource
{
    public function viewAbility(): string
    {
        return Ability::BOOKINGS_MANAGE;
    }

    /** المدرّس يرى حجوزات الخدمات التي يقدّمها هو. */
    public function scopeFor(Builder $query, ?User $user): Builder
    {
        if (! app(Roles::class)->isScoped($user)) {
            return $query;
        }

        return $query->whereHas('provider', fn ($q) => $q->where('user_id', $user?->getKey()));
    }

    public function feature(): ?string
    {
        return 'services_module';
    }

    public function model(): string
    {
        return Booking::class;
    }

    public function label(): string
    {
        return __('الحجوزات');
    }

    public function singularLabel(): string
    {
        return __('حجز');
    }

    public function query(): Builder
    {
        return Booking::query()->with(['service', 'provider.user', 'user']);
    }

    /** صفحة الحجز العامة هي شاشة تفاصيله — لا نبني ثانية بنفس البيانات. */
    public function recordUrl(Model $record, string $key): ?string
    {
        return url('/bookings/'.$record->token);
    }

    public function columns(): array
    {
        return [
            TextColumn::make('reference')->label(__('الرقم'))->searchable()->mono(),

            TextColumn::make('service.title')->label(__('الخدمة'))->wrap()
                ->using(fn ($v, Booking $b): string => (string) ($b->service?->title ?? '—')),

            TextColumn::make('customer_name')->label(__('العميل'))->searchable()
                ->using(fn ($v, Booking $b): string => $b->customerName()),

            DateColumn::make('date')->label(__('الموعد'))->sortable(),

            TextColumn::make('starts_at')->label(__('الساعة'))->mono()
                ->using(fn ($v): string => $v === null ? '—' : substr((string) $v, 0, 5)),

            BadgeColumn::make('status')->label(__('الحالة'))->sortable()
                ->tones([
                    'confirmed' => 'success', 'completed' => 'success',
                    'pending' => 'warning', 'in_progress' => 'info', 'delivered' => 'info',
                    'cancelled' => 'danger', 'no_show' => 'danger',
                ])
                ->labels(array_map(fn (string $l): string => __($l), Booking::STATUSES)),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->label(__('الحالة'))
                ->options(array_map(fn (string $l): string => __($l), Booking::STATUSES)),

            SelectFilter::make('service_id')->label(__('الخدمة'))
                ->options(Service::published()->pluck('title', 'id')
                    ->map(fn ($title): string => (string) $title)->all()),
        ];
    }

    public function form(): array
    {
        return [
            Section::make(__('متابعة الحجز'))->fields([
                SelectField::make('status')->label(__('الحالة'))->half()->required()
                    ->options(array_map(fn (string $l): string => __($l), Booking::STATUSES)),
                TextField::make('meeting_url')->label(__('رابط الجلسة'))->url()
                    ->hint(__('يظهر للعميل عند تأكيد الحجز.')),
                TextareaField::make('notes')->label(__('ملاحظات داخلية')),
                TextField::make('cancel_reason')->label(__('سبب الإلغاء'))->half(),
            ]),
        ];
    }

    public function emptyState(): array
    {
        return [
            'title' => __('لا حجوزات بعد'),
            'body' => __('انشر خدمة واضبط ساعات مقدّميها ليبدأ الحجز.'),
        ];
    }
}
