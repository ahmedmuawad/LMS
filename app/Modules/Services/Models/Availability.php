<?php

declare(strict_types=1);

namespace App\Modules\Services\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ساعات العمل الأسبوعية لمقدّم الخدمة. */
final class Availability extends Model
{
    protected $table = 'service_availability';

    protected $guarded = [];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }
}
