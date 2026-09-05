<?php

declare(strict_types=1);

namespace App\Modules\Live\Models;

use Illuminate\Database\Eloquent\Model;

/** غرفةٌ أنشأها مزوّدٌ خارجي لحصةٍ أو مجموعة. */
final class LiveRoom extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
