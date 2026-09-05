<?php

declare(strict_types=1);

namespace App\Modules\Ai\Models;

use App\Models\User;
use App\Modules\Lms\Models\Lesson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** رسالةٌ في محادثة المساعد الدراسي. */
final class AiMessage extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
