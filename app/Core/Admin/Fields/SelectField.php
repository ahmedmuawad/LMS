<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

final class SelectField extends Field
{
    /** @var array<string, string> */
    private array $options = [];

    private ?string $placeholder = null;

    /** @param  array<string, string>  $options */
    public function options(array $options): self
    {
        $this->options = $options;
        // القيم المقبولة محصورة في الخيارات المعلَنة
        $this->rules[] = 'in:'.implode(',', array_keys($options));

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function component(): string
    {
        return 'admin.fields.select';
    }

    public function props(): array
    {
        return ['options' => $this->options, 'placeholder' => $this->placeholder];
    }
}
