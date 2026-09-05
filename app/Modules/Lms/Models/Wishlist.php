<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use App\Modules\Commerce\Models\Product;
use App\Modules\Services\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * أمنية: كورس أو خدمة أو منتج أجّل الطالب شراءه.
 *
 * النوع يُقيَّد بقائمة مغلقة لا بأي اسم صنف يصل من الطلب: تمرير
 * اسم الصنف من الخارج يفتح باباً لاستدعاء أي موديل في التطبيق.
 */
final class Wishlist extends Model
{
    /** الأنواع المسموحة — قائمة مغلقة عمداً */
    public const TYPES = [
        'course' => Course::class,
        'service' => Service::class,
        'product' => Product::class,
    ];

    /** @var list<string> */
    protected $fillable = ['itemable_type', 'itemable_id', 'price_minor_at_add', 'currency'];

    protected function casts(): array
    {
        return [
            'itemable_id' => 'integer',
            'price_minor_at_add' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, int|string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** صنف الموديل لهذا النوع، أو null إن لم يكن من القائمة المغلقة */
    public static function modelFor(string $type): ?string
    {
        return self::TYPES[$type] ?? null;
    }

    /** العنصر نفسه — يُحمَّل عند الحاجة، وقد يكون حُذف بعد الإضافة */
    public function item(): ?Model
    {
        $class = self::modelFor($this->itemable_type);

        return $class === null ? null : $class::find($this->itemable_id);
    }

    public function typeLabel(): string
    {
        return match ($this->itemable_type) {
            'course' => __('كورس'),
            'service' => __('خدمة'),
            'product' => __('منتج'),
            default => __('عنصر'),
        };
    }
}
