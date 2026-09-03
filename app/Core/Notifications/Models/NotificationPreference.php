<?php

declare(strict_types=1);

namespace App\Core\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تفضيل يُخزَّن عند المخالفة فقط.
 *
 * صفّ لكل مستخدم × حدث × قناة يعني ملايين الصفوف بلا فائدة:
 * غياب الصفّ يعني «كما ضبطه المشترك».
 */
final class NotificationPreference extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
