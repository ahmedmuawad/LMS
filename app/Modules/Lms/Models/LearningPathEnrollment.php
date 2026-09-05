<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** تسجيل طالبٍ في مسار. */
final class LearningPathEnrollment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function path(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class, 'path_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
