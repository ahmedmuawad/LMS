<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** صوت واحد لكل شخص على كل عنصر — القيد الفريد هو ما يمنع التلاعب. */
final class DiscussionVote extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    public function votable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
