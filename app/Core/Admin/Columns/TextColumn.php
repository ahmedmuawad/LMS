<?php

declare(strict_types=1);

namespace App\Core\Admin\Columns;

use Illuminate\Database\Eloquent\Model;

final class TextColumn extends Column
{
    private ?string $description = null;

    private ?int $limit = null;

    private bool $mono = false;

    public function description(string $attribute): self
    {
        $this->description = $attribute;

        return $this;
    }

    public function limit(int $characters): self
    {
        $this->limit = $characters;

        return $this;
    }

    public function mono(bool $mono = true): self
    {
        $this->mono = $mono;

        return $this;
    }

    public function component(): string
    {
        return 'admin.columns.text';
    }

    public function props(Model $record): array
    {
        return [
            'description' => $this->description ? data_get($record, $this->description) : null,
            'limit' => $this->limit,
            'mono' => $this->mono,
        ];
    }
}
