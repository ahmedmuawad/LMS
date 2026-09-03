<?php

declare(strict_types=1);

namespace App\Modules\Growth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AffiliateClick extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['converted_at' => 'datetime'];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
