<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * تاريخ — بمنتقي المتصفّح لا بحقل نصّي.
 *
 * الحقل النصّي يطالب المستخدم بأن يحزر الصيغة، ويقبل «١٢/١/٢٠٢٦» على
 * أنها يناير أو ديسمبر بحسب من يقرأها. `input[type=date]` يعرض التقويم
 * بلغة المتصفّح ويرسل `Y-m-d` دائماً — فالعرض محليّ والقيمة معياريّة.
 */
final class DateField extends Field
{
    private bool $withTime = false;

    private ?string $min = null;

    private ?string $max = null;

    /** يضيف الساعة والدقيقة — للمواعيد لا للأيام. */
    public function withTime(bool $with = true): self
    {
        $this->withTime = $with;

        return $this;
    }

    public function min(string $date): self
    {
        $this->min = $date;

        return $this;
    }

    public function max(string $date): self
    {
        $this->max = $date;

        return $this;
    }

    /** الحدّ الأدنى هو اليوم — للتواريخ التي لا معنى لها في الماضي. */
    public function notBefore(string $date = 'today'): self
    {
        $this->min = Carbon::parse($date)->format($this->storageFormat());
        $this->rules[] = 'after_or_equal:'.$this->min;

        return $this;
    }

    public function component(): string
    {
        return 'admin.fields.date';
    }

    public function props(): array
    {
        return [
            'type' => $this->withTime ? 'datetime-local' : 'date',
            'min' => $this->min,
            'max' => $this->max,
        ];
    }

    public function validationRules(string $context): array
    {
        $rules = parent::validationRules($context);
        $rules[] = 'date';

        return array_values(array_unique($rules));
    }

    /**
     * `input[type=date]` لا يقبل إلا `Y-m-d`، و`datetime-local` إلا
     * `Y-m-d\TH:i`. والقيمة المخزّنة قد تأتي نصّاً أو Carbon أو بصيغة
     * القاعدة الكاملة — فنوحّدها هنا بدل أن يخرج الحقل فارغاً بصمت.
     */
    public function valueFor(?Model $record): mixed
    {
        return $this->normalise(parent::valueFor($record));
    }

    public function fill(mixed $input): mixed
    {
        if ($input === null || $input === '') {
            return null;
        }

        return $this->normalise($input) ?? $input;
    }

    private function normalise(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse(is_string($raw) ? $raw : (string) $raw)->format($this->storageFormat());
        } catch (Throwable) {
            // قيمة لا تُقرأ كتاريخ: نعيدها كما هي ليمسكها التحقّق برسالة مفهومة
            return is_string($raw) ? $raw : null;
        }
    }

    private function storageFormat(): string
    {
        return $this->withTime ? 'Y-m-d\TH:i' : 'Y-m-d';
    }
}
