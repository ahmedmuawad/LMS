<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** تسجيل طالبٍ في فعالية. */
final class EventRegistration extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['registered_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
