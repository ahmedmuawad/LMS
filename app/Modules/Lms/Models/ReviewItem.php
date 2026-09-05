<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سؤالٌ أخطأ فيه طالب، يعود إليه حتى يُتقنه. */
final class ReviewItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'mastered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('mastered_at');
    }

    /**
     * ترتيب المراجعة: الأكثر خطأً أوّلاً، ثم الأقدم عهداً.
     *
     * ما أخطأ فيه ثلاث مرّات أولى بوقته ممّا أخطأ فيه مرّة. وعند
     * التساوي يُقدَّم ما لم يره منذ زمن — فالنسيان يُقاس بالوقت.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->pending()
            ->orderByDesc('wrong_count')
            ->orderByRaw('last_seen_at is null desc')
            ->orderBy('last_seen_at');
    }

    public function isMastered(): bool
    {
        return $this->mastered_at !== null;
    }
}
