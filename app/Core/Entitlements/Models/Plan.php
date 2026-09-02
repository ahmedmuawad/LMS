<?php

declare(strict_types=1);

namespace App\Core\Entitlements\Models;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Plan extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'tagline' => 'array',
            'prices' => 'array',
            'modes' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'plan_key', 'key');
    }

    /**
     * ADR-014 — التسعير مثبّت لكل عملة، لا محوّل بسعر الصرف،
     * حتى تكون الأرقام «جميلة» ومحترمة للقوة الشرائية.
     */
    public function priceIn(string $currency): ?Money
    {
        $minor = $this->prices[strtoupper($currency)] ?? null;

        return $minor === null ? null : Money::fromMinor((int) $minor, $currency);
    }

    public function supportsMode(string $mode): bool
    {
        return blank($this->modes) || in_array($mode, $this->modes, true);
    }
}
