<?php

declare(strict_types=1);

namespace App\Modules\Api\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * مفتاح واجهة برمجية.
 *
 * لا يُخزَّن نصّاً: يُعرض مرة واحدة عند إنشائه ثم تُحفظ تجزئته
 * وحدها. فتسريبُ قاعدة البيانات لا يسرّب مفاتيح أحد — ومنصّةٌ
 * تحفظ المفاتيح نصّاً تجعل نسخةً احتياطية مسروقة كارثةً مضاعفة.
 */
final class ApiToken extends Model
{
    /** النطاقات المتاحة — قائمة مغلقة لا نصّ حرّ */
    public const SCOPES = [
        'courses:read' => 'قراءة الكورسات',
        'students:read' => 'قراءة الطلبة',
        'enrollments:read' => 'قراءة التسجيلات',
        'enrollments:write' => 'تسجيل الطلبة',
        'groups:read' => 'قراءة المجموعات',
        'attendance:read' => 'قراءة الحضور',
        'invoices:read' => 'قراءة الفواتير',
    ];

    /** @var list<string> */
    protected $fillable = ['user_id', 'name', 'prefix', 'token_hash', 'scopes', 'expires_at'];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ينشئ مفتاحاً جديداً ويعيد نصّه مرة واحدة.
     *
     * @param  list<string>  $scopes
     * @return array{token: ApiToken, plain: string}
     */
    public static function issue(User $user, string $name, array $scopes, ?string $expiresAt = null): array
    {
        // بادئةٌ مقروءة تُميّز المفتاح في السجلّات والقوائم بلا كشفه
        $plain = 'usos_'.Str::random(48);

        $token = self::create([
            'user_id' => $user->getKey(),
            'name' => $name,
            'prefix' => mb_substr($plain, 0, 12),
            'token_hash' => hash('sha256', $plain),
            'scopes' => array_values(array_intersect($scopes, array_keys(self::SCOPES))),
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    /**
     * يُطابق مفتاحاً وارداً.
     *
     * المطابقة بالتجزئة في القاعدة لا بمرورٍ على الصفوف: مقارنةُ
     * كل صفّ تُطيل الزمن بعدد المفاتيح، وتكشف عددها لمن يقيس.
     */
    public static function match(string $plain): ?self
    {
        return self::where('token_hash', hash('sha256', $plain))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }

    public function allows(string $scope): bool
    {
        return in_array($scope, (array) $this->scopes, true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** «usos_a1b2c3…» — ما يُعرض في القائمة بدل المفتاح */
    public function masked(): string
    {
        return $this->prefix.'…';
    }
}
