<?php

declare(strict_types=1);

namespace App\Models;

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

    /** الأدوار التي تفتح لوحة التحكم — الطالب وولي الأمر لهما بواباتهما. */
    public const PANEL_ROLES = ['owner', 'admin', 'instructor', 'staff'];

    public function canAccessPanel(): bool
    {
        return $this->status === 'active' && in_array($this->role, self::PANEL_ROLES, true);
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
