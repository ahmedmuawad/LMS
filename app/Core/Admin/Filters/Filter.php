<?php

declare(strict_types=1);

namespace App\Core\Admin\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;

abstract class Filter
{
    protected ?string $label = null;

    final public function __construct(public readonly string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? __(str_replace('_', ' ', $this->name));
    }

    abstract public function apply(Builder $query, mixed $value): Builder;

    abstract public function component(): string;

    /** @return array<string, mixed> */
    abstract public function props(): array;
}
