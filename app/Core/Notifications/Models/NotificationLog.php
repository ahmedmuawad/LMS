<?php

declare(strict_types=1);

namespace App\Core\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجلّ ما أُرسل فعلاً.
 *
 * «ما وصلني إشعار» أكثر شكوى في أنظمة السناتر، وبغير سجلّ يقول
 * متى أُرسل وإلى أين وبأي نتيجة لا يُحسم النقاش أبداً.
 */
final class NotificationLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
