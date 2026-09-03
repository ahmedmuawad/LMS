<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Instructor extends Model
{
    use HasTranslations;

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['headline', 'bio'];

    protected function casts(): array
    {
        return [
            'headline' => 'array',
            'bio' => 'array',
            'expertise' => 'array',
            'social' => 'array',
            'payout_method' => 'array',
            'is_verified' => 'boolean',
            'approved_at' => 'datetime',
            'commission_rate' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function name(): string
    {
        return (string) ($this->user?->name ?? __('مدرّس'));
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }
}
