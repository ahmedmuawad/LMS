<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قيد نقاط واحد.
 *
 * النقطة تُقيَّد بسببها ومصدرها لا مجموعاً وحده: مجموع بلا تفصيل
 * لا يُراجَع ولا يُصحَّح حين تُخطئ قاعدة أو يتلاعب أحد.
 */
final class PointEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
