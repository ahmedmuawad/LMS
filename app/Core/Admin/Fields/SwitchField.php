<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

final class SwitchField extends Field
{
    public function component(): string
    {
        return 'admin.fields.switch';
    }

    public function validationRules(string $context): array
    {
        return ['nullable', 'boolean'];
    }

    public function fill(mixed $input): mixed
    {
        return (bool) $input;
    }
}
