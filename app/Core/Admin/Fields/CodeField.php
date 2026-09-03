<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

/** كود أو قالب — خط ثابت العرض وسطور كثيرة، بلا تصحيح إملائي. */
final class CodeField extends Field
{
    private int $rows = 10;

    public function rows(int $rows): self
    {
        $this->rows = $rows;

        return $this;
    }

    public function component(): string
    {
        return 'admin.fields.code';
    }

    public function props(): array
    {
        return ['rows' => $this->rows];
    }

    public function validationRules(string $context): array
    {
        return ['nullable', 'string', 'max:65535'];
    }
}
