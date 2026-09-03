<?php

declare(strict_types=1);

namespace App\Core\Settings;

use App\Core\Admin\Fields\Field;
use App\Core\Admin\Fields\Section;

/**
 * وثيقة 05 — مجموعة إعدادات واحدة = شاشة واحدة في اللوحة.
 *
 * المبدأ الحاكم: لكل إعداد قيمة افتراضية عاقلة، فالمنصة تعمل كاملة
 * قبل أن يُفتح أي من هذه الشاشات.
 */
abstract class SettingsGroup
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function icon(): string;

    /** @return list<Section> */
    abstract public function sections(): array;

    public function description(): ?string
    {
        return null;
    }

    /** موديول يجب أن يكون مفعّلاً حتى تظهر الشاشة — null تعني: تظهر دائماً. */
    public function module(): ?string
    {
        return null;
    }

    /** @return list<Field> كل حقول المجموعة مسطّحة */
    public function fields(): array
    {
        return array_merge(...array_map(
            fn (Section $section): array => $section->getFields(),
            $this->sections(),
        ));
    }

    /** @return array<string, list<string>> قواعد التحقق لكل حقول المجموعة */
    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->fields() as $field) {
            $rules[$field->name] = $field->validationRules('edit');

            if (method_exists($field, 'itemRules')) {
                $rules = [...$rules, ...$field->itemRules()];
            }
        }

        return $rules;
    }

    /** @return array<string, string> أسماء الحقول بالعربية لرسائل التحقق */
    public function validationAttributes(): array
    {
        $names = [];

        foreach ($this->fields() as $field) {
            $names[$field->name] = $field->getLabel();
        }

        return $names;
    }

    /** @return array<string, mixed> القيم الحالية، بالافتراضي حيث لا قيمة محفوظة */
    public function values(): array
    {
        $stored = setting()->group($this->key());
        $values = [];

        foreach ($this->fields() as $field) {
            $values[$field->name] = $stored[$field->name] ?? $field->getDefault();
        }

        return $values;
    }

    /** @return list<string> الحقول السرّية — تُخزَّن مشفّرة ولا تُعاد للمتصفح */
    public function secretFields(): array
    {
        return array_values(array_map(
            fn (Field $field): string => $field->name,
            array_filter($this->fields(), fn (Field $field): bool => method_exists($field, 'isSecret') && $field->isSecret()),
        ));
    }
}
