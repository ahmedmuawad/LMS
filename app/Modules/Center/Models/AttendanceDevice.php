<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * جهاز حضور: بصمة أو كارت أو QR.
 *
 * مفتاحه لا يُخزَّن نصّاً — والجهاز في ممرّ السنتر أسهل وصولاً من
 * خادم: من فكّه وقرأ ذاكرته لا ينبغي أن يحصل على مفتاحٍ يعمل.
 */
final class AttendanceDevice extends Model
{
    public const KINDS = [
        'fingerprint' => 'بصمة',
        'card' => 'بطاقة',
        'qr' => 'ماسح QR',
        'turnstile' => 'بوّابة',
    ];

    /** @var list<string> */
    protected $fillable = ['name', 'kind', 'branch_id', 'room_id', 'prefix', 'token_hash', 'is_active'];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_seen_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(DevicePunch::class, 'device_id');
    }

    /** @return array{device: AttendanceDevice, plain: string} */
    public static function register(string $name, string $kind, ?int $branchId = null, ?int $roomId = null): array
    {
        $plain = 'dev_'.Str::random(40);

        $device = self::create([
            'name' => $name,
            'kind' => $kind,
            'branch_id' => $branchId,
            'room_id' => $roomId,
            'prefix' => mb_substr($plain, 0, 12),
            'token_hash' => hash('sha256', $plain),
        ]);

        return ['device' => $device, 'plain' => $plain];
    }

    public static function match(string $plain): ?self
    {
        return self::where('token_hash', hash('sha256', $plain))->where('is_active', true)->first();
    }

    public function masked(): string
    {
        return $this->prefix.'…';
    }

    public function kindLabel(): string
    {
        return __(self::KINDS[$this->kind] ?? $this->kind);
    }
}
