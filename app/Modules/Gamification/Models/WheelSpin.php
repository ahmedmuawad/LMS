<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** دورةُ عجلةٍ يومية. */
final class WheelSpin extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['spun_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
