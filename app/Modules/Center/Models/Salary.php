<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Salary extends Model
{
    public const TYPES = [
        'fixed' => 'راتب ثابت',
        'per_session' => 'بالحصة',
        'percentage' => 'نسبة من التحصيل',
    ];

    public const STATUSES = ['draft' => 'مسودّة', 'approved' => 'معتمد', 'paid' => 'مصروف'];

    protected $table = 'center_salaries';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'rate' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function net(): Money
    {
        return Money::fromMinor((int) $this->net_minor, $this->currency);
    }
}
