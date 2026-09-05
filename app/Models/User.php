<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Access\Roles;
use App\Core\Auth\TwoFactor;
use App\Modules\Gamification\Models\Badge;
use App\Modules\Gamification\Models\LearningStreak;
use App\Modules\Lms\Models\MomentResponse;
use App\Modules\Lms\Models\Note;
use App\Modules\Lms\Models\Wishlist;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;

/**
 * مستخدم داخل قاعدة المشترك: طالب أو مدرّس أو موظف.
 * لا يوجد عمود tenant_id — العزل على مستوى القاعدة نفسها (ADR-009).
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    protected $guarded = [];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

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

    /**
     * التحقّق من البريد يُشترط إن طلبه المشترك وحده.
     *
     * Laravel يسأل هذه قبل حراسة `verified`؛ وإرجاع true دائماً
     * يُقفل موقعاً أطفأ الشرط عمداً — كسنتر يُدخل طلابه يدوياً.
     */
    public function hasVerifiedEmail(): bool
    {
        return ! (bool) setting('users.verify_email', true) || $this->email_verified_at !== null;
    }

    /** هل انتهت صلاحية كلمة المرور بحكم سياسة المشترك؟ */
    public function passwordHasExpired(): bool
    {
        $days = (int) setting('users.password_expires_days', 0);

        if ($days <= 0) {
            return false;
        }

        $changed = $this->password_changed_at ?? $this->created_at;

        return $changed !== null && $changed->addDays($days)->isPast();
    }

    public function twoFactorEnabled(): bool
    {
        return app(TwoFactor::class)->isEnabled($this);
    }

    /**
     * رسائل المصادقة تمرّ بمُرسِلنا لا بمُرسِل Laravel.
     *
     * وإلا لخرجت من المنصّة رسالتان بقالبين: قوالب المشترك المترجَمة
     * القابلة للتحرير لكل شيء، وقالب Laravel الإنجليزي الثابت
     * لأهمّ رسالتين — تأكيد البريد واستعادة كلمة المرور.
     */
    public function sendEmailVerificationNotification(): void
    {
        $minutes = (int) config('auth.verification.expire', 60);

        notify('account.verify_email', $this, [
            'verify_url' => URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes($minutes),
                ['id' => $this->getKey(), 'hash' => sha1((string) $this->email)],
            ),
            'expires_in' => trans_choice(
                '{1} ساعة|{2} ساعتان|[3,10] :count ساعات|[11,*] :count ساعة',
                max(1, intdiv($minutes, 60)),
                ['count' => max(1, intdiv($minutes, 60))],
            ),
            'url' => url('/verify-email'),
        ]);
    }

    public function sendPasswordResetNotification($token): void
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);

        notify('account.reset_password', $this, [
            'reset_url' => url('/reset-password/'.$token.'?email='.urlencode((string) $this->email)),
            'expires_in' => trans_choice(
                '{1} دقيقة|{2} دقيقتان|[3,10] :count دقائق|[11,*] :count دقيقة',
                $minutes,
                ['count' => $minutes],
            ),
            'url' => url('/forgot-password'),
        ]);
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

    /** ملاحظاته أثناء التعلّم — تُنشأ عبر العلاقة فيُملأ `user_id` من الجالس لا من الطلب */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function wishlist(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /** إجاباته على نقاط التفاعل — تُنشأ عبر العلاقة فيُملأ user_id من الجالس */
    public function momentResponses(): HasMany
    {
        return $this->hasMany(MomentResponse::class);
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
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'terms_accepted' => 'boolean',
        ];
    }
}
