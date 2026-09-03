<?php

declare(strict_types=1);

namespace App\Modules\Services\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ServiceProvider extends Model
{
    protected $table = 'service_providers';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'commission_rate' => 'float'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function availability(): HasMany
    {
        return $this->hasMany(Availability::class, 'provider_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class, 'provider_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'provider_id');
    }

    public function name(): string
    {
        return (string) ($this->user?->name ?? __('مقدّم الخدمة'));
    }
}
