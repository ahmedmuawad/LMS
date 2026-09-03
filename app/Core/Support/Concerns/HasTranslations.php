<?php

declare(strict_types=1);

namespace App\Core\Support\Concerns;

/**
 * حقول مترجمة داخل عمود json.
 *
 *     $course->title                     → نصّ لغة العرض مع الاحتياط
 *     $course->getTranslations('title')  → المصفوفة كاملة (لشاشة التحرير)
 *
 * السبب: صفٌّ واحد للكورس بكل لغاته — لا جدول ترجمات يتضاعف معه
 * كل استعلام، ولا صفّ ترجمة يُنسى فيختفي الكورس من لغة.
 */
trait HasTranslations
{
    /**
     * نخزّن العربية كما هي لا كـ \uXXXX.
     *
     * الهروب الافتراضي يجعل البحث بـ LIKE على نصّ عربي لا يطابق
     * شيئاً، ويجعل قراءة الصف في القاعدة مستحيلة على إنسان.
     */
    protected function asJson($value, $flags = 0)
    {
        return parent::asJson($value, $flags | JSON_UNESCAPED_UNICODE);
    }

    /** @return list<string> */
    public function translatable(): array
    {
        return $this->translatable ?? [];
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (! in_array($key, $this->translatable(), true) || ! is_array($value)) {
            return $value;
        }

        return $this->pickTranslation($value);
    }

    /** @return array<string, string> */
    public function getTranslations(string $key): array
    {
        $value = $this->attributes[$key] ?? null;

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    public function getTranslation(string $key, string $locale): ?string
    {
        $value = $this->getTranslations($key)[$locale] ?? null;

        return $value === '' ? null : $value;
    }

    /** @param  array<string, string>  $value */
    public function setTranslations(string $key, array $value): static
    {
        $this->attributes[$key] = json_encode(
            array_filter($value, fn ($v): bool => $v !== null && $v !== ''),
            JSON_UNESCAPED_UNICODE,
        );

        return $this;
    }

    /** @param  array<string, string>  $value */
    private function pickTranslation(array $value): ?string
    {
        $locale = app()->getLocale();
        $fallback = config('locales.default', 'ar');

        foreach ([$locale, $fallback] as $candidate) {
            if (filled($value[$candidate] ?? null)) {
                return $value[$candidate];
            }
        }

        // آخر ملاذ: أي لغة مكتوبة — النص بلغة أخرى أفضل من فراغ
        $written = array_filter($value, fn ($v): bool => filled($v));

        return $written === [] ? null : (string) reset($written);
    }
}
