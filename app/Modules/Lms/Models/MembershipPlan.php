<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * باقة عضوية يبيعها المدرّس لطلابه.
 */
final class MembershipPlan extends Model
{
    use HasTranslations;

    public const PERIODS = [
        'month' => 'شهرياً',
        'quarter' => 'كل ثلاثة أشهر',
        'year' => 'سنوياً',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'course_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'plan_id');
    }

    public function price(): Money
    {
        return Money::fromMinor((int) $this->price_minor, (string) $this->currency);
    }

    /** هل تفتح هذه الباقة هذا الكورس؟ */
    public function covers(Course $course): bool
    {
        if ($this->scope === 'all') {
            return true;
        }

        return in_array($course->getKey(), (array) $this->course_ids, true);
    }

    public function periodLabel(): string
    {
        return __(self::PERIODS[$this->period] ?? $this->period);
    }

    /** كم يوماً تدوم الدورة — للتجديد */
    public function days(): int
    {
        return match ($this->period) {
            'quarter' => 90,
            'year' => 365,
            default => 30,
        };
    }
}
