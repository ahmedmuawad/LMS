<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

final class TextareaField extends Field
{
    private int $rows = 4;

    private ?string $placeholder = null;

    public function rows(int $rows): self
    {
        $this->rows = $rows;

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function component(): string
    {
        return 'admin.fields.textarea';
    }

    public function props(): array
    {
        return ['rows' => $this->rows, 'placeholder' => $this->placeholder, 'math' => $this->math];
    }
}
