<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** صنفٌ في مخزن السنتر: كتاب أو مذكّرة أو أداة. */
final class InventoryItem extends Model
{
    public const KINDS = [
        'notes' => 'مذكّرة',
        'book' => 'كتاب',
        'tool' => 'أداة',
        'other' => 'أخرى',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_sellable' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'item_id');
    }

    public function kindLabel(): string
    {
        return __(self::KINDS[$this->kind] ?? $this->kind);
    }

    public function price(): Money
    {
        return Money::minor((int) $this->price_minor);
    }

    /** تحت حدّ التنبيه — يُنبَّه صاحب السنتر قبل أن ينفد */
    public function isLow(): bool
    {
        return (int) $this->reorder_level > 0 && (int) $this->stock_qty <= (int) $this->reorder_level;
    }

    public function scopeLow(Builder $query): Builder
    {
        return $query->where('reorder_level', '>', 0)
            ->whereColumn('stock_qty', '<=', 'reorder_level');
    }

    /**
     * الرصيد من الحركات — للجرد لا للعرض.
     *
     * العمود يُسرِّع القوائم، وهذا يكشف اختلافه: لو افترقا فثمّة
     * حركةٌ كُتبت بلا تحديث الرصيد، أو رصيدٌ عُدّل بلا حركة.
     */
    public function countedStock(): int
    {
        return (int) $this->movements()->sum('qty');
    }
}
