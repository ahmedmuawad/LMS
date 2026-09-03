<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use App\Modules\Lms\Models\Instructor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Payout extends Model
{
    public const STATUSES = [
        'pending' => 'مطلوب',
        'processing' => 'قيد التحويل',
        'paid' => 'محوَّل',
        'failed' => 'فشل',
    ];

    public const METHODS = [
        'bank' => 'تحويل بنكي',
        'vodafone_cash' => 'فودافون كاش',
        'instapay' => 'إنستاباي',
        'cash' => 'نقدي',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['destination' => 'array', 'paid_at' => 'datetime'];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(InstructorEarning::class);
    }

    public function amount(): Money
    {
        return Money::fromMinor((int) $this->amount_minor, $this->currency);
    }
}
