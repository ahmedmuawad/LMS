<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $status
 * @property array|null $snapshot
 */
final class QuizAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'evaluated_at' => 'datetime',
            'passed' => 'boolean',
            'score' => 'float',
            'max_score' => 'float',
            'percentage' => 'float',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'in_progress';
    }

    /** الوقت المتبقّي بالثواني — null يعني اختباراً بلا وقت. */
    public function secondsLeft(): ?int
    {
        $limit = (int) ($this->quiz?->time_limit_minutes ?? 0);

        if ($limit === 0 || $this->started_at === null) {
            return null;
        }

        return max(0, $limit * 60 - (int) $this->started_at->diffInSeconds(now()));
    }

    public function hasRunOut(): bool
    {
        $left = $this->secondsLeft();

        return $left !== null && $left <= 0;
    }

    /** هل بقي سؤال بانتظار تصحيح بشري؟ */
    public function awaitsGrading(): bool
    {
        return $this->answers()->whereNull('is_correct')->exists();
    }
}
