<?php

declare(strict_types=1);

namespace App\Core\Admin\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;

final class BooleanFilter extends Filter
{
    private ?string $column = null;

    public function column(string $column): self
    {
        $this->column = $column;

        return $this;
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (! in_array($value, ['1', '0', 1, 0, true, false], true)) {
            return $query;
        }

        return $query->where($this->column ?? $this->name, (bool) $value);
    }

    public function component(): string
    {
        return 'admin.filters.select';
    }

    public function props(): array
    {
        return [
            'options' => ['1' => __('نعم'), '0' => __('لا')],
            'placeholder' => __('الكل'),
        ];
    }
}
