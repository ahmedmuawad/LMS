<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserDevice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_trusted' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** هل هذا هو الجهاز الذي يقرأ الشاشة الآن؟ لا يُعرض له زرّ فصل */
    public function isCurrent(string $fingerprint): bool
    {
        return hash_equals((string) $this->fingerprint, $fingerprint);
    }
}
