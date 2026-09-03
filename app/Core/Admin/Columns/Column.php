<?php

declare(strict_types=1);

namespace App\Core\Admin\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * عمود في جدول مورد. يُعرَّف في PHP ويُصيَّر بمكوّنات نظام التصميم،
 * فتخرج كل الجداول في المنصة بنفس السلوك والشكل.
 */
abstract class Column
{
    protected ?string $label = null;

    protected bool $sortable = false;

    protected bool $searchable = false;

    protected ?Closure $using = null;

    protected string $align = 'start';

    protected bool $wrap = false;

    protected ?string $width = null;

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

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    /** تحويل القيمة قبل العرض. */
    public function using(Closure $callback): static
    {
        $this->using = $callback;

        return $this;
    }

    public function align(string $align): static
    {
        $this->align = $align;

        return $this;
    }

    public function wrap(bool $wrap = true): static
    {
        $this->wrap = $wrap;

        return $this;
    }

    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? __(str_replace('_', ' ', $this->name));
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function getAlign(): string
    {
        return $this->align;
    }

    public function shouldWrap(): bool
    {
        return $this->wrap;
    }

    public function getWidth(): ?string
    {
        return $this->width;
    }

    public function value(Model $record): mixed
    {
        $value = data_get($record, $this->name);

        return $this->using ? ($this->using)($value, $record) : $value;
    }

    /** اسم مكوّن Blade الذي يصيّر هذا العمود. */
    abstract public function component(): string;

    /** @return array<string, mixed> خصائص إضافية للمكوّن */
    public function props(Model $record): array
    {
        return [];
    }
}
