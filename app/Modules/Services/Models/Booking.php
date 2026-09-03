<?php

declare(strict_types=1);

namespace App\Modules\Services\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $status
 */
final class Booking extends Model
{
    public const STATUSES = [
        'pending' => 'بانتظار التأكيد',
        'confirmed' => 'مؤكَّد',
        'in_progress' => 'قيد التنفيذ',
        'delivered' => 'سُلِّم',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغى',
        'no_show' => 'لم يحضر',
    ];

    /** الحالات التي تحجز الوقت فعلاً. */
    public const BLOCKING = ['pending', 'confirmed', 'in_progress'];

    protected $guarded = [];

    /** الرمز يُولَّد هنا لا عند كل مُنشئ: نسيانه مرّة يكشف حجزاً. */
    protected static function booted(): void
    {
        self::creating(function (self $booking): void {
            $booking->token ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'date' => 'date', 'intake' => 'array', 'deliverables' => 'array',
            'confirmed_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', self::BLOCKING);
    }

    public function price(): Money
    {
        return Money::fromMinor((int) $this->price_minor, $this->currency);
    }

    public function startsAtCarbon(): ?Carbon
    {
        return $this->date === null || $this->starts_at === null
            ? null
            : $this->date->copy()->setTimeFromTimeString((string) $this->starts_at);
    }

    /** الإلغاء المجاني ينتهي قبل الموعد بمهلة الخدمة. */
    public function canCancelFreely(): bool
    {
        $start = $this->startsAtCarbon();
        $hours = (int) ($this->service?->cancel_hours ?? 0);

        return $start === null || $start->copy()->subHours($hours)->isFuture();
    }

    public function customerName(): string
    {
        return (string) ($this->user?->name ?? $this->customer_name ?? __('عميل'));
    }
}
