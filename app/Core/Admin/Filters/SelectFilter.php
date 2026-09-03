<?php

declare(strict_types=1);

namespace App\Core\Admin\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;

final class SelectFilter extends Filter
{
    /** @var array<string, string> */
    private array $options = [];

    private ?string $column = null;

    private ?string $placeholder = null;

    /** @param  array<string, string>  $options */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function column(string $column): self
    {
        $this->column = $column;

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        // القيم المسموحة محصورة في الخيارات المعرّفة — لا يمرّ إدخال حرّ إلى الاستعلام
        if ($value === null || $value === '' || ! array_key_exists((string) $value, $this->options)) {
            return $query;
        }

        return $query->where($this->column ?? $this->name, $value);
    }

    public function component(): string
    {
        return 'admin.filters.select';
    }

    public function props(): array
    {
        return [
            'options' => $this->options,
            'placeholder' => $this->placeholder ?? __('الكل'),
        ];
    }
}
