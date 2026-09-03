<?php

declare(strict_types=1);

namespace App\Core\Admin\Columns;

use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;

final class NumberColumn extends Column
{
    private ?string $currency = null;

    private int $decimals = 0;

    private ?string $suffix = null;

    /** يعرض العمود كمبلغ بأصغر وحدة عبر طبقة Money (ADR-014). */
    public function money(string $currency): self
    {
        $this->currency = $currency;
        $this->align = 'end';

        return $this;
    }

    public function decimals(int $decimals): self
    {
        $this->decimals = $decimals;

        return $this;
    }

    public function suffix(string $suffix): self
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function component(): string
    {
        return 'admin.columns.number';
    }

    public function props(Model $record): array
    {
        $raw = data_get($record, $this->name);

        return [
            'display' => $this->currency !== null && $raw !== null
                ? Money::fromMinor((int) $raw, $this->currency)->format()
                : ($raw === null ? null : number_format((float) $raw, $this->decimals).($this->suffix ? ' '.$this->suffix : '')),
        ];
    }
}
