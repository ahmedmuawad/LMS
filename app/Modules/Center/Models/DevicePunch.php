<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بصمةٌ خام وصلت من جهاز.
 *
 * تُحفظ حتى إن لم تُطابَق: صاحب السنتر يسأل «الجهاز شغّال؟»،
 * وسجلٌّ فيه «كودٌ غير معروف» يجيب بنعم ويدلّ على الخطأ الحقيقي.
 */
final class DevicePunch extends Model
{
    public const UPDATED_AT = null;

    public const RESULTS = [
        'matched' => 'سُجّل',
        'unknown_code' => 'كود غير معروف',
        'no_session' => 'لا حصة الآن',
        'duplicate' => 'مسجَّل يدوياً من قبل',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['punched_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function resultLabel(): string
    {
        return __(self::RESULTS[$this->result] ?? $this->result);
    }

    public function resultTone(): string
    {
        return match ($this->result) {
            'matched' => 'success',
            'duplicate' => 'info',
            default => 'warning',
        };
    }
}
