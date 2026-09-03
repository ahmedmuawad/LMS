<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Mark extends Model
{
    protected $table = 'center_marks';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['marks' => 'float', 'is_absent' => 'boolean'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function percentage(): ?float
    {
        $max = (float) ($this->assessment?->max_marks ?? 0);

        return $this->marks === null || $max <= 0 ? null : round($this->marks / $max * 100, 1);
    }
}
