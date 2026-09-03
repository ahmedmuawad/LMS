<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\User;
use App\Modules\Services\Models\Booking;
use App\Modules\Services\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ServiceReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'replied_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }
}
