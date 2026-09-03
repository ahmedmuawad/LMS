<?php

declare(strict_types=1);

namespace App\Modules\Growth\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * تسلسل تسويقي: خطوات مؤجَّلة تُرسل لمن دخله.
 *
 * من حقّق الهدف يخرج فوراً — إرسال «أكمل شراءك» لمن اشترى بالفعل
 * لا يُزعج وحده بل يُفقد الثقة في كل رسالة بعده.
 */
final class Campaign extends Model
{
    use HasTranslations;

    public const TRIGGERS = [
        'cart_abandoned' => 'سلة متروكة',
        'course_idle' => 'خمول في كورس',
        'access_expiring' => 'قرب انتهاء الوصول',
        'course_completed' => 'إتمام كورس',
        'signup' => 'حساب جديد',
        'booking_upcoming' => 'موعد قادم',
        'manual' => 'إدخال يدوي',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return ['name' => 'array', 'conditions' => 'array'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(CampaignStep::class)->orderBy('position');
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(CampaignEnrolment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function conversionRate(): float
    {
        return $this->entered_count === 0
            ? 0.0
            : round((int) $this->converted_count / (int) $this->entered_count * 100, 1);
    }
}
