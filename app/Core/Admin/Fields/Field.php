<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

/**
 * حقل في نموذج مورد. يحمل تسميته وتلميحه وقواعد تحققه معاً،
 * فلا تتفرّق قاعدة التحقق عن الحقل الذي تحرسه.
 */
abstract class Field
{
    protected ?string $label = null;

    protected ?string $hint = null;

    protected bool $required = false;

    protected mixed $default = null;

    /** @var list<string> */
    protected array $rules = [];

    protected string $span = 'full';    // full | half

    protected bool $onlyOnCreate = false;

    protected bool $skipWhenEmpty = false;

    final public function __construct(public readonly string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function hint(string $hint): static
    {
        $this->hint = $hint;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    /**
     * تُضيف قواعد ولا تستبدل ما أضافه نوع الحقل نفسه.
     * الاستبدال يمحو قواعد ضمنية بصمت — مثل قاعدة البريد التي
     * يضيفها email() فتضيع بأول استدعاء لاحق لـ rules().
     *
     * @param  list<string>|string  $rules
     */
    public function rules(array|string $rules): static
    {
        $this->rules = [...$this->rules, ...(is_string($rules) ? explode('|', $rules) : $rules)];

        return $this;
    }

    /** استبدال صريح لكل القواعد — عند الحاجة الحقيقية فقط. */
    public function replaceRules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function half(): static
    {
        $this->span = 'half';

        return $this;
    }

    public function onlyOnCreate(bool $only = true): static
    {
        $this->onlyOnCreate = $only;

        return $this;
    }

    /** حقل يُترك فارغاً عمداً للإبقاء على القيمة القائمة (كلمة المرور مثلاً). */
    public function skipWhenEmpty(bool $skip = true): static
    {
        $this->skipWhenEmpty = $skip;

        return $this;
    }

    public function shouldSkipWhenEmpty(): bool
    {
        return $this->skipWhenEmpty;
    }

    public function getLabel(): string
    {
        return $this->label ?? __(str_replace('_', ' ', $this->name));
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getSpan(): string
    {
        return $this->span;
    }

    public function showsOn(string $context): bool
    {
        return $context === 'create' || ! $this->onlyOnCreate;
    }

    /** @return list<string> قواعد التحقق النهائية لهذا الحقل */
    public function validationRules(string $context): array
    {
        $rules = $this->rules;

        array_unshift($rules, $this->required && $context === 'create' ? 'required' : 'nullable');

        return array_values(array_unique($rules));
    }

    public function valueFor(?Model $record): mixed
    {
        return $record !== null ? data_get($record, $this->name) : $this->default;
    }

    /** تحويل المُدخل قبل الحفظ. */
    public function fill(mixed $input): mixed
    {
        return $input;
    }

    abstract public function component(): string;

    /** @return array<string, mixed> */
    public function props(): array
    {
        return [];
    }
}
