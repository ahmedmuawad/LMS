<?php

declare(strict_types=1);

namespace App\Modules\Growth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CampaignEnrolment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['next_step_at' => 'datetime', 'converted_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CampaignSend::class, 'enrolment_id');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', 'running')
            ->whereNotNull('next_step_at')
            ->where('next_step_at', '<=', now());
    }
}
