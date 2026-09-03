<?php

declare(strict_types=1);

namespace App\Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * استثناء يوم بعينه: إجازة، أو ساعات إضافية.
 * فصله عن القالب يمنع إعادة بناء الأسبوع كله لأجل يوم واحد.
 */
final class AvailabilityException extends Model
{
    protected $table = 'service_exceptions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_available' => 'boolean'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }
}
