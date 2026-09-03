<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuizAnswer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'answer' => 'array',
            'instructor_note' => 'array',
            'is_correct' => 'boolean',
            'marks_awarded' => 'float',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
