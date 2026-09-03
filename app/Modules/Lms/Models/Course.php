<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string|null $title
 * @property string $status
 */
final class Course extends Model
{
    use HasTranslations;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'مسودّة',
        'pending' => 'بانتظار الاعتماد',
        'published' => 'منشور',
        'archived' => 'مؤرشف',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'excerpt', 'description'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'excerpt' => 'array',
            'description' => 'array',
            'requirements' => 'array',
            'outcomes' => 'array',
            'target_audience' => 'array',
            'seo' => 'array',
            'certificate_enabled' => 'boolean',
            'sequential' => 'boolean',
            'drip_enabled' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------
    // العلاقات
    // ---------------------------------------------------------------

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'category_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'level_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('position');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CourseItem::class)->orderBy('position');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }

    // ---------------------------------------------------------------
    // النطاقات
    // ---------------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('visibility', 'public');
    }

    /** ما يراه هذا المستخدم: المنشور، وما يملكه هو إن كان مدرّسه. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->canAccessPanel() === true) {
            return $query;
        }

        return $query->published();
    }

    // ---------------------------------------------------------------
    // المنطق
    // ---------------------------------------------------------------

    public function price(): Money
    {
        return Money::fromMinor((int) $this->price_minor, $this->currency ?? tenant('currency') ?? 'EGP');
    }

    public function isFree(): bool
    {
        return $this->enrollment_type === 'free' || (int) $this->price_minor === 0;
    }

    public function isOpenForEnrollment(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        if ($this->starts_at?->isFuture() || $this->ends_at?->isPast()) {
            return false;
        }

        return $this->max_students === null || $this->students_count < $this->max_students;
    }

    /** متى ينتهي وصول من يسجّل الآن — null تعني مدى الحياة. */
    public function accessEndsAt(): ?Carbon
    {
        $days = (int) $this->access_days;

        return $days > 0 ? now()->addDays($days) : null;
    }

    public function isEnrolled(?User $user): bool
    {
        return $user !== null && $this->enrollments()->where('user_id', $user->getKey())->exists();
    }
}
