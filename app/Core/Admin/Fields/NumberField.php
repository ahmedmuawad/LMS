<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;

final class NumberField extends Field
{
    private ?string $currency = null;

    private ?string $suffix = null;

    private int|float|null $min = null;

    private int|float|null $max = null;

    /** يستقبل مبلغاً عشرياً ويخزّنه بأصغر وحدة (ADR-014). */
    public function money(string $currency): self
    {
        $this->currency = $currency;
        $this->rules[] = 'numeric';
        $this->min ??= 0;

        return $this;
    }

    public function suffix(string $suffix): self
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function range(int|float|null $min = null, int|float|null $max = null): self
    {
        $this->min = $min;
        $this->max = $max;

        return $this;
    }

    public function component(): string
    {
        return 'admin.fields.number';
    }

    public function props(): array
    {
        return ['min' => $this->min, 'max' => $this->max, 'suffix' => $this->suffix ?? $this->currency];
    }

    public function validationRules(string $context): array
    {
        $rules = parent::validationRules($context);
        $rules[] = 'numeric';

        if ($this->min !== null) {
            $rules[] = 'min:'.$this->min;
        }

        if ($this->max !== null) {
            $rules[] = 'max:'.$this->max;
        }

        return array_values(array_unique($rules));
    }

    public function valueFor(?Model $record): mixed
    {
        $raw = parent::valueFor($record);

        if ($this->currency === null || $raw === null) {
            return $raw;
        }

        return Money::fromMinor((int) $raw, $this->currency)->toDecimal();
    }

    public function fill(mixed $input): mixed
    {
        if ($this->currency === null || $input === null || $input === '') {
            return $input === '' ? null : $input;
        }

        return Money::fromDecimal((string) $input, $this->currency)->minor;
    }
}
