<?php

declare(strict_types=1);

namespace App\Core\Admin\Columns;

use Illuminate\Database\Eloquent\Model;

final class DateColumn extends Column
{
    private string $format = 'Y-m-d';

    private bool $relative = false;

    public function format(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    /** «منذ ٣ أيام» بدل التاريخ المطلق. */
    public function relative(bool $relative = true): self
    {
        $this->relative = $relative;

        return $this;
    }

    public function component(): string
    {
        return 'admin.columns.date';
    }

    public function props(Model $record): array
    {
        $value = data_get($record, $this->name);

        return [
            'iso' => $value?->toIso8601String(),
            'display' => $value === null
                ? null
                : ($this->relative ? $value->diffForHumans() : $value->format($this->format)),
        ];
    }
}
