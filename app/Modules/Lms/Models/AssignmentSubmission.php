<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssignmentSubmission extends Model
{
    public const STATUSES = [
        'pending' => 'بانتظار التصحيح',
        'graded' => 'مصحّح',
        'resubmit' => 'مطلوب إعادة التسليم',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'feedback' => 'array',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'is_late' => 'boolean',
            'marks' => 'float',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function passed(): bool
    {
        return $this->marks !== null && $this->marks >= (float) ($this->assignment?->passing_marks ?? 0);
    }
}
