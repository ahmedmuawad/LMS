<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * مظلة واحدة لكل ما يُباع.
 *
 * الكورس والخدمة والمنتج المادي أشياء مختلفة تماماً في التسليم،
 * لكنها واحدة في السلة والطلب والتقرير — وهذا ما يجمعه هذا الجدول.
 */
final class Product extends Model
{
    use HasTranslations;
    use SoftDeletes;

    public const TYPES = [
        'course' => 'كورس',
        'bundle' => 'حزمة',
        'subscription' => 'اشتراك',
        'service' => 'خدمة',
        'digital' => 'منتج رقمي',
        'physical' => 'منتج مادي',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'short_description', 'description'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'short_description' => 'array',
            'description' => 'array',
            'gallery' => 'array',
            'dimensions' => 'array',
            'seo' => 'array',
            'is_taxable' => 'boolean',
            'manage_stock' => 'boolean',
            'allow_backorder' => 'boolean',
            'requires_shipping' => 'boolean',
            'featured' => 'boolean',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
        ];
    }

    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** السعر النافذ الآن: سعر التخفيض إن كان ضمن مدّته. */
    public function price(): Money
    {
        return Money::fromMinor($this->effectiveMinor(), $this->currency);
    }

    public function fullPrice(): Money
    {
        return Money::fromMinor((int) $this->price_minor, $this->currency);
    }

    public function isOnSale(): bool
    {
        return $this->effectiveMinor() < (int) $this->price_minor;
    }

    public function isFree(): bool
    {
        return $this->effectiveMinor() === 0;
    }

    private function effectiveMinor(): int
    {
        $sale = $this->sale_price_minor;

        if ($sale === null) {
            return (int) $this->price_minor;
        }

        $started = $this->sale_starts_at === null || $this->sale_starts_at->isPast();
        $running = $this->sale_ends_at === null || $this->sale_ends_at->isFuture();

        return $started && $running ? (int) $sale : (int) $this->price_minor;
    }

    /**
     * هل يمكن شراء هذه الكمية الآن؟
     * ما لا يُدار مخزونه (كورس · خدمة) لا ينفد أبداً.
     */
    public function canSell(int $quantity = 1): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        if (! $this->manage_stock) {
            return true;
        }

        return $this->allow_backorder || $this->stock_qty >= $quantity;
    }

    public function isOutOfStock(): bool
    {
        return $this->manage_stock && ! $this->allow_backorder && $this->stock_qty <= 0;
    }
}
