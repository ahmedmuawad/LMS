<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductVariant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function price(): Money
    {
        return $this->price_minor === null
            ? $this->product->price()
            : Money::fromMinor((int) $this->price_minor, $this->product->currency);
    }

    public function label(): string
    {
        return implode(' · ', array_map(
            fn (string $key, string $value): string => $key.': '.$value,
            array_keys($this->options ?? []),
            array_values($this->options ?? []),
        ));
    }
}
