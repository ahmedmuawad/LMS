<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RechargeBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function codes(): HasMany
    {
        return $this->hasMany(RechargeCode::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function value(): Money
    {
        return Money::fromMinor((int) $this->value_minor, $this->currency ?? tenant('currency') ?? 'EGP');
    }

    public function usedCount(): int
    {
        return $this->codes()->where('status', 'used')->count();
    }
}
