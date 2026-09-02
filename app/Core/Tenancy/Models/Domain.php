<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * نطاق فرعي لكل مشترك دائماً، ونطاق خاص حسب الباقة (ميزة custom_domain).
 */
final class Domain extends BaseDomain
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function isCustom(): bool
    {
        return $this->type === 'custom';
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
