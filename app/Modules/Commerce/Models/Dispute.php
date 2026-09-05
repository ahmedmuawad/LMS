<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نزاعٌ فتحه المشتري لدى بنكه.
 *
 * وهو غير الاسترداد: الاسترداد قرار المشترك، والنزاع قرار البنك —
 * المال يُسحب فوراً، ويُطلب دليلٌ خلال مدّة، ومن لم يردّ خسِر المبلغ
 * ورسمَ النزاع فوقه.
 */
final class Dispute extends Model
{
    public const STATUSES = [
        'open' => 'مفتوح',
        'under_review' => 'قيد المراجعة',
        'won' => 'كُسب',
        'lost' => 'خُسر',
        'refunded' => 'رُدّ المبلغ',
    ];

    /** متى يصير قربُ المهلة إنذاراً */
    public const URGENT_DAYS = 3;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'due_by' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'under_review']);
    }

    public function amount(): Money
    {
        return Money::fromMinor((int) $this->amount_minor, (string) $this->currency);
    }

    /** هل بقي على المهلة أقلّ من ثلاثة أيام؟ */
    public function isUrgent(): bool
    {
        if ($this->due_by === null || $this->resolved_at !== null) {
            return false;
        }

        return $this->due_by->isBefore(now()->addDays(self::URGENT_DAYS));
    }

    public function isOverdue(): bool
    {
        return $this->due_by !== null
            && $this->resolved_at === null
            && $this->due_by->isPast();
    }
}
