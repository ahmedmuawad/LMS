<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

final class Assignment extends Model
{
    use HasTranslations;

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'instructions'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'instructions' => 'array',
            'attachments' => 'array',
            'allowed_extensions' => 'array',
            'allow_late' => 'boolean',
            'max_marks' => 'float',
            'passing_marks' => 'float',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function items(): MorphMany
    {
        return $this->morphMany(CourseItem::class, 'itemable');
    }

    public function dueFor(?Carbon $enrolledAt): ?Carbon
    {
        $days = (int) $this->due_days;

        return $days > 0 && $enrolledAt !== null ? $enrolledAt->copy()->addDays($days) : null;
    }

    /** خصم التأخير يُطبَّق على الدرجة، ولا يُنزلها تحت الصفر. */
    public function applyLatePenalty(float $marks): float
    {
        $penalty = (int) $this->late_penalty_percent;

        return $penalty === 0 ? $marks : max(0, round($marks * (1 - $penalty / 100), 2));
    }
}
