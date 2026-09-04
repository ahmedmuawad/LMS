<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

/**
 * نصّ بلغتين في حقل واحد.
 *
 * فصل الحقول لغةً لغةً يضاعف الشاشة ويجعل نسيان الترجمة هو الافتراضي؛
 * جمعهما هنا يجعل الفراغ مرئياً وقت الكتابة.
 */
final class TranslatableField extends Field
{
    private bool $long = false;

    public function long(bool $long = true): self
    {
        $this->long = $long;

        return $this;
    }

    public function component(): string
    {
        return 'admin.fields.translatable';
    }

    public function props(): array
    {
        return ['locales' => array_keys(config('locales.supported', ['ar' => [], 'en' => []])), 'long' => $this->long, 'math' => $this->math];
    }

    public function validationRules(string $context): array
    {
        return ['nullable', 'array'];
    }

    /** @return array<string, list<string>> */
    public function itemRules(): array
    {
        $rules = [];

        foreach (array_keys(config('locales.supported', ['ar' => [], 'en' => []])) as $locale) {
            $rules[$this->name.'.'.$locale] = ['nullable', 'string', 'max:1000'];
        }

        return $rules;
    }

    /**
     * المصفوفة كاملةً لا نصّ لغة العرض.
     *
     * `getAttribute` على حقل مترجَم يُعيد **نصّ لغة واحدة** — وهو
     * الصواب في العرض والخطأ القاتل في التحرير: الشاشة كانت تفتح
     * بحقول فارغة، فمن يحفظ بعد تعديل الصعوبة وحدها يمحو عنوان
     * الكورس ونصّ السؤال بلغاته كلها.
     *
     * @return array<string, string>
     */
    public function valueFor(?Model $record): mixed
    {
        if ($record === null) {
            return (array) ($this->default ?? []);
        }

        return method_exists($record, 'getTranslations')
            ? $record->getTranslations($this->name)
            : (array) data_get($record, $this->name);
    }

    public function fill(mixed $input): mixed
    {
        return array_filter((array) $input, fn ($v): bool => $v !== null && $v !== '');
    }
}
