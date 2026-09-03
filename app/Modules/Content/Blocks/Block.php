<?php

declare(strict_types=1);

namespace App\Modules\Content\Blocks;

use App\Core\Admin\Fields\Field;

/**
 * ADR-005 — الصفحة تُبنى بكتل لا بمحرّر نصّي حرّ.
 *
 * الكتلة تُترجَم وتُعاد ترتيباً وتبقى متجاوبة ويمكن قياسها؛ والنصّ
 * الحرّ المُلصَق من Word يكسر الأربعة دفعةً واحدة.
 */
abstract class Block
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function icon(): string;

    /** @return list<Field> حقول تحرير الكتلة */
    abstract public function fields(): array;

    /** @return array<string, mixed> القيم الافتراضية لكتلة جديدة */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->fields() as $field) {
            $defaults[$field->name] = $field->getDefault();
        }

        return $defaults;
    }

    public function group(): string
    {
        return 'general';
    }

    public function view(): string
    {
        return 'blocks.'.$this->key();
    }

    /** @return array<string, list<string>> قواعد التحقق لمحتوى الكتلة */
    public function rules(string $prefix): array
    {
        $rules = [];

        foreach ($this->fields() as $field) {
            $rules[$prefix.'.'.$field->name] = $field->validationRules('edit');

            if (method_exists($field, 'itemRules')) {
                foreach ($field->itemRules() as $name => $itemRules) {
                    $rules[$prefix.'.'.$name] = $itemRules;
                }
            }
        }

        return $rules;
    }

    /**
     * تنقية المحتوى قبل الحفظ: نقبل ما تعرّفه الكتلة فقط.
     * ما لم يُعرَّف حقلاً لا يُخزَّن — فلا يتسلّل HTML عبر مفتاح مجهول.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function sanitize(array $content): array
    {
        $clean = [];

        foreach ($this->fields() as $field) {
            if (array_key_exists($field->name, $content)) {
                $clean[$field->name] = $field->fill($content[$field->name]);
            }
        }

        return $clean;
    }
}
