<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Access\Roles;
use App\Modules\Gamification\Models\Badge;
use App\Modules\Gamification\Models\LearningStreak;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * مستخدم داخل قاعدة المشترك: طالب أو مدرّس أو موظف.
 * لا يوجد عمود tenant_id — العزل على مستوى القاعدة نفسها (ADR-009).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    /**
     * الأدوار التي تفتح اللوحة — تُقرأ من config لا تُكرّر هنا.
     *
     * @return list<string>
     */
    public static function panelRoles(): array
    {
        return (array) config('roles.panel', []);
    }

    public function canAccessPanel(): bool
    {
        return app(Roles::class)->mayEnterPanel($this);
    }

    /** هل يملك هذه الصلاحية؟ المصدر واحد لكل حراسة في المشروع. */
    public function allows(string $ability): bool
    {
        return app(Roles::class)->allows($this, $ability);
    }

    /** الدور المحصور يرى ما يملكه وحده. */
    public function isScoped(): bool
    {
        return app(Roles::class)->isScoped($this);
    }

    public function roleLabel(): string
    {
        return app(Roles::class)->label((string) $this->role);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_user')->withPivot('awarded_at');
    }

    public function streak(): HasOne
    {
        return $this->hasOne(LearningStreak::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'legacy_hash' => 'boolean',
            'meta' => 'array',
        ];
    }
}
