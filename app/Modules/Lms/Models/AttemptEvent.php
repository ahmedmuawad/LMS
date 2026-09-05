<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حدثٌ وقع أثناء محاولة امتحان مُراقَبة.
 *
 * بلا `updated_at`: السطر يُكتب مرة ولا يُعدَّل — وسجلٌّ يُعدَّل لا
 * يصلح دليلاً.
 */
final class AttemptEvent extends Model
{
    public const UPDATED_AT = null;

    public const KINDS = [
        'blur' => 'خرج من النافذة',
        'hidden' => 'أخفى الصفحة',
        'paste' => 'لصق نصّاً',
        'copy' => 'نسخ نصّاً',
    ];

    /** @var list<string> */
    protected $fillable = ['attempt_id', 'kind', 'at_second'];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function label(): string
    {
        return __(self::KINDS[$this->kind] ?? $this->kind);
    }

    /** موضعه من بداية الامتحان، مقروءاً */
    public function atLabel(): string
    {
        $s = (int) $this->at_second;

        return sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    }
}
