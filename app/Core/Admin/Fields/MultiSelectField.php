<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

/** اختيار متعدّد بمربّعات — أوضح من قائمة متعدّدة الاختيار وأسهل باللمس. */
final class MultiSelectField extends Field
{
    /** @var array<string, string> */
    private array $options = [];

    private int $columns = 2;

    /** @param  array<string, string>  $options */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function columns(int $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * حقل العلاقة يُعيد المعرّفات لا النماذج.
     *
     * `data_get($record, 'skills')` تُعيد مجموعةَ نماذج، والشاشة
     * تقارنها نصّاً بمفاتيح الخيارات — فلا يظهر شيءٌ مُعلَّماً وإن
     * كان مربوطاً.
     */
    public function valueFor(?Model $record): mixed
    {
        if (! $this->isRelation() || $record === null) {
            return parent::valueFor($record);
        }

        $related = $record->{$this->name};

        return $related === null ? [] : $related->pluck('id')->all();
    }

    public function component(): string
    {
        return 'admin.fields.multi-select';
    }

    public function props(): array
    {
        return ['options' => $this->options, 'columns' => $this->columns];
    }

    public function validationRules(string $context): array
    {
        return ['nullable', 'array'];
    }

    /** @return array<string, list<string>> قواعد العناصر — تُدمج مع قاعدة الحقل */
    public function itemRules(): array
    {
        return [$this->name.'.*' => ['string', 'in:'.implode(',', array_keys($this->options))]];
    }

    public function fill(mixed $input): mixed
    {
        return array_values(array_filter((array) $input));
    }
}
