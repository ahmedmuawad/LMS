<?php

declare(strict_types=1);

namespace App\Core\Admin\Columns;

use Illuminate\Database\Eloquent\Model;

final class BadgeColumn extends Column
{
    /** @var array<string, string> */
    private array $tones = [];

    /** @var array<string, string> */
    private array $labels = [];

    /** @param  array<string, string>  $map  قيمة => نغمة */
    public function tones(array $map): self
    {
        $this->tones = $map;

        return $this;
    }

    /** @param  array<string, string>  $map  قيمة => نص معروض */
    public function labels(array $map): self
    {
        $this->labels = $map;

        return $this;
    }

    public function component(): string
    {
        return 'admin.columns.badge';
    }

    public function props(Model $record): array
    {
        $value = (string) data_get($record, $this->name);

        return [
            'tone' => $this->tones[$value] ?? 'neutral',
            'text' => $this->labels[$value] ?? $value,
        ];
    }
}
