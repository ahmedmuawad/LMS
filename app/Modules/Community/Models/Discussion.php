<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\User;
use App\Modules\Lms\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * سؤال أو نقاش داخل كورس.
 *
 * الطالب الذي يجد من يسأله يكمل الكورس، والذي لا يجد يتوقّف عند
 * أول عائق — فهذا جزء من معدّل الإتمام لا زينة اجتماعية.
 */
final class Discussion extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'question' => 'سؤال', 'discussion' => 'نقاش', 'announcement' => 'إعلان',
    ];

    public const STATUSES = [
        'open' => 'مفتوح', 'answered' => 'أُجيب', 'closed' => 'مغلق', 'hidden' => 'مخفيّ',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'last_reply_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionReply::class)->where('status', 'visible');
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(DiscussionVote::class, 'votable');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', '!=', 'hidden');
    }

    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->where('type', 'question')->where('status', 'open');
    }

    public function isAnswered(): bool
    {
        return $this->status === 'answered';
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->getKey();
    }
}
