<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Cashbox extends Model
{
    use HasTranslations;

    protected $table = 'center_cashboxes';

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return ['name' => 'array', 'is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function closings(): HasMany
    {
        return $this->hasMany(CashClosing::class);
    }

    public function balance(): Money
    {
        return Money::fromMinor((int) $this->balance_minor, $this->currency);
    }
}
