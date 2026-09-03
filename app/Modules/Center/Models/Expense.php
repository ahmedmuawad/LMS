<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Expense extends Model
{
    public const CATEGORIES = [
        'rent' => 'إيجار', 'salaries' => 'رواتب', 'utilities' => 'مرافق',
        'maintenance' => 'صيانة', 'supplies' => 'مستلزمات', 'marketing' => 'تسويق',
        'transport' => 'مواصلات', 'other' => 'أخرى',
    ];

    protected $table = 'center_expenses';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['spent_on' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function amount(): Money
    {
        return Money::fromMinor((int) $this->amount_minor, $this->currency);
    }
}
