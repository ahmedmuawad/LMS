<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $status
 * @property int $progress_percent
 */
final class Enrollment extends Model
{
    public const STATUSES = [
        'active' => 'نشط',
        'completed' => 'مكتمل',
        'expired' => 'منتهي الصلاحية',
        'suspended' => 'موقوف',
        'refunded' => 'مستردّ',
    ];

    public const SOURCES = [
        'purchase' => 'شراء',
        'manual' => 'إضافة يدوية',
        'bundle' => 'ضمن حزمة',
        'subscription' => 'اشتراك',
        'import' => 'استيراد',
        'free' => 'مجاني',
        'code' => 'كود شحن',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'grade' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'id', 'enrollment_id');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'completed']);
    }

    /**
     * هل يصل الطالب إلى محتواه الآن؟
     *
     * انتهاء المدة يمنع الجديد ولا يمحو ما أنجز: سجلّه ودرجاته
     * وشهادته تبقى — الطالب دفع، ولا نعاقبه بانتهاء اشتراك.
     */
    public function hasAccess(): bool
    {
        if (in_array($this->status, ['suspended', 'refunded'], true)) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function daysLeft(): ?int
    {
        return $this->expires_at === null ? null : max(0, (int) ceil(now()->floatDiffInDays($this->expires_at, false)));
    }
}
