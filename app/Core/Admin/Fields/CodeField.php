<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

/** كود أو قالب — خط ثابت العرض وسطور كثيرة، بلا تصحيح إملائي. */
final class CodeField extends Field
{
    private int $rows = 10;

    private bool $json = false;

    public function rows(int $rows): self
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * الحقل يحمل بنية JSON لا نصّاً حرّاً.
     *
     * يُعرض منسّقاً ويُخزَّن مصفوفةً: تخزينه نصّاً يجعل كل قارئ
     * لاحق يفكّه بنفسه، وأول من ينسى يكسر الشاشة.
     */
    public function json(bool $json = true): self
    {
        $this->json = $json;

        return $this;
    }

    public function component(): string
    {
        return 'admin.fields.code';
    }

    public function props(): array
    {
        return ['rows' => $this->rows, 'json' => $this->json];
    }

    public function validationRules(string $context): array
    {
        $rules = ['nullable', 'string', 'max:65535'];

        if ($this->json) {
            $rules[] = 'json';
        }

        return $rules;
    }

    public function valueFor(?Model $record): mixed
    {
        $value = parent::valueFor($record);

        if (! $this->json || $value === null || is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function fill(mixed $input): mixed
    {
        if (! $this->json) {
            return $input;
        }

        $decoded = is_string($input) ? json_decode($input, true) : $input;

        return is_array($decoded) ? $decoded : [];
    }
}
