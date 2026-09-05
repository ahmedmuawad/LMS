<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** حركةٌ على صنف: دخول أو خروج أو عهدة أو تلف. */
final class InventoryMovement extends Model
{
    /** النوع => [الاسم، هل يزيد الرصيد] */
    public const TYPES = [
        'in' => ['توريد', true],
        'sale' => ['بيع', false],
        'out' => ['صرف', false],
        'custody' => ['عهدة', false],
        'return' => ['ردّ عهدة', true],
        'damaged' => ['تالف', false],
        'lost' => ['مفقود', false],
        'count' => ['تسوية جرد', true],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['returned_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function typeLabel(): string
    {
        return __(self::TYPES[$this->type][0] ?? $this->type);
    }

    /** عهدةٌ لم تُردّ بعد */
    public function isOpenCustody(): bool
    {
        return $this->type === 'custody' && $this->returned_at === null;
    }

    /** من عنده العهدة — طالبٌ أو موظّف */
    public function holder(): string
    {
        return (string) ($this->student?->name ?? $this->staff?->name ?? __('غير محدّد'));
    }
}
