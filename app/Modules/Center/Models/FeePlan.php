<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FeePlan extends Model
{
    use HasTranslations;

    protected $table = 'center_fee_plans';

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return ['name' => 'array', 'is_active' => 'boolean', 'late_fee_percent' => 'float'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function amount(): Money
    {
        return Money::fromMinor((int) $this->amount_minor, $this->currency);
    }

    /** غرامة التأخير تُحسب بعد مهلة السماح لا من يوم الاستحقاق. */
    public function lateFeeOn(Money $amount, int $daysLate): Money
    {
        if ($this->late_fee_percent <= 0 || $daysLate <= (int) $this->grace_days) {
            return Money::zero($amount->currency);
        }

        return $amount->percentage($this->late_fee_percent);
    }
}
