<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

/** مجموعة حقول تحت عنوان — تُبقي النموذج الطويل مقروءاً. */
final class Section
{
    /** @var list<Field> */
    private array $fields = [];

    private ?string $description = null;

    private function __construct(public readonly string $title) {}

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /** @param  list<Field>  $fields */
    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /** @return list<Field> */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
