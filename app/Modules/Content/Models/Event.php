<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Models\User;
use App\Modules\Lms\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** فعالية: ندوة أو ورشة أو يوم امتحان أو إجازة. */
final class Event extends Model
{
    use HasTranslations;

    public const KINDS = [
        'workshop' => 'ورشة',
        'webinar' => 'ندوة أونلاين',
        'exam' => 'يوم امتحان',
        'meeting' => 'لقاء',
        'holiday' => 'إجازة',
        'other' => 'أخرى',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'description'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_published' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** القادمة: ما لم ينتهِ بعد — لا ما لم يبدأ */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('ends_at', '>=', now())
                ->orWhere(fn (Builder $c) => $c->whereNull('ends_at')->where('starts_at', '>=', now()->startOfDay()));
        });
    }

    public function kindLabel(): string
    {
        return __(self::KINDS[$this->kind] ?? $this->kind);
    }

    public function takesRegistrations(): bool
    {
        return (int) $this->capacity > 0;
    }

    public function seatsLeft(): int
    {
        return max(0, (int) $this->capacity - (int) $this->registered_count);
    }

    public function isFull(): bool
    {
        return $this->takesRegistrations() && $this->seatsLeft() < 1;
    }

    public function hasPassed(): bool
    {
        return ($this->ends_at ?? $this->starts_at)?->isPast() ?? false;
    }

    public function isRegistered(?User $user): bool
    {
        return $user !== null && $this->registrations()
            ->where('user_id', $user->getKey())
            ->where('status', '!=', 'cancelled')
            ->exists();
    }

    /**
     * الموعد مقروءاً.
     *
     * ويومٌ واحد يُكتب مرّةً لا مرّتين: «٥ سبتمبر ١٠:٠٠ — ٥ سبتمبر
     * ١٢:٠٠» يُقرأ مرّتين ليُفهم أنه يومٌ واحد.
     */
    public function whenLabel(): string
    {
        $start = $this->starts_at?->translatedFormat('l j F · H:i') ?? '';

        if ($this->ends_at === null) {
            return $start;
        }

        return $this->ends_at->isSameDay($this->starts_at)
            ? $start.' – '.$this->ends_at->format('H:i')
            : $start.' → '.$this->ends_at->translatedFormat('l j F · H:i');
    }
}
