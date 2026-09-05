<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * عضوية طالب: اشتراكٌ دوريّ يفتح محتوى بدل شراء كل كورس.
 */
final class Membership extends Model
{
    public const STATUSES = [
        'trialing' => 'تجربة',
        'active' => 'سارية',
        'past_due' => 'متأخّرة السداد',
        'cancelled' => 'ملغاة',
        'expired' => 'منتهية',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'renews_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    /**
     * هل تفتح المحتوى الآن؟
     *
     * الملغاة تبقى فاتحةً حتى نهاية ما دُفع: القطعُ الفوري سرقةٌ لما
     * دُفع، ويُحوّل إلغاءً هادئاً إلى شكوى.
     */
    public function isLive(): bool
    {
        if (in_array($this->status, ['active', 'trialing'], true)) {
            return true;
        }

        return $this->status === 'cancelled'
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }

    public function grants(Course $course): bool
    {
        return $this->isLive() && $this->plan?->covers($course) === true;
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereIn('status', ['active', 'trialing'])
                ->orWhere(fn (Builder $c) => $c->where('status', 'cancelled')->where('ends_at', '>', now()));
        });
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'trialing' => 'info',
            'past_due' => 'danger',
            'cancelled' => $this->isLive() ? 'warning' : 'neutral',
            default => 'neutral',
        };
    }

    /** متى تنتهي فعلاً — للملغاة نهايةُ ما دُفع، ولغيرها موعد التجديد */
    public function endsOn(): ?Carbon
    {
        return $this->ends_at ?? $this->renews_at;
    }
}
