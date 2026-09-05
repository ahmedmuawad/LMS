<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مفتاح مرورٍ مسجَّل لمستخدم.
 *
 * والمفتاح الخاصّ ليس هنا ولا يصلنا أبداً: يبقى في الجهاز، ونحفظ
 * العامّ وعدّاد التوقيع. فتسريبُ قاعدتنا لا يُدخل أحداً.
 */
final class Passkey extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id', 'label', 'credential_id', 'credential', 'sign_count', 'last_used_at',
    ];

    /** ما لا يخرج في JSON: الوصف الكامل شأنٌ داخلي */
    protected $hidden = ['credential'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
