<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CartItem extends Model
{
    protected $guarded = [];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function unitPrice(): Money
    {
        return Money::fromMinor((int) $this->unit_price_minor, $this->cart?->currency ?? tenant('currency') ?? 'EGP');
    }

    public function total(): Money
    {
        return $this->unitPrice()->times($this->quantity);
    }
}
