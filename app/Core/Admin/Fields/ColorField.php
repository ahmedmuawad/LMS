<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

/** لون بصيغة #RRGGBB — منتقي لوني إلى جانب حقل نصّي، فالنسخ واللصق أسرع من الانتقاء. */
final class ColorField extends Field
{
    public function component(): string
    {
        return 'admin.fields.color';
    }

    public function validationRules(string $context): array
    {
        return [$this->isRequired() ? 'required' : 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
    }
}
