<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

/**
 * خيارات السؤال وإجابته الصحيحة في حقل واحد.
 *
 * كانت شاشة بنك الأسئلة تحفظ نصّ السؤال ونوعه ودرجته، ولا تحفظ
 * **خياراته ولا إجابته** — فلم يكن يمكن كتابة سؤال اختيار من اللوحة
 * أصلاً، إنما من الكود. وبنك أسئلة لا يُكتب من شاشته ليس بنكاً.
 *
 * الخيار والصواب حقل واحد لا اثنان: فصلهما يعني نموذجاً يسمح بحفظ
 * إجابة صحيحة تشير إلى خيار محذوف.
 */
final class ChoicesField extends Field
{
    private string $typeField = 'type';

    /** اسم الحقل الذي يحمل نوع السؤال — به تُقرّر الواجهة شكل الاختيار. */
    public function dependsOn(string $field): self
    {
        $this->typeField = $field;

        return $this;
    }

    public function component(): string
    {
        return 'admin.fields.choices';
    }

    public function props(): array
    {
        return ['typeField' => $this->typeField];
    }

    public function validationRules(string $context): array
    {
        return ['nullable', 'array'];
    }

    /**
     * الخيارات ومعها الصواب — الشاشة تحتاجهما معاً لترسم العلامة.
     *
     * @return array{options: array<string,string>, correct: list<string>}
     */
    public function valueFor(?Model $record): mixed
    {
        return [
            'options' => (array) ($record === null ? [] : data_get($record, $this->name) ?? []),
            'correct' => array_map('strval', (array) ($record === null ? [] : data_get($record, 'correct') ?? [])),
        ];
    }

    /**
     * يصل من الشاشة `[['key' => 'a', 'text' => '…', 'correct' => '1'], …]`
     * ويُحفَظ خريطةً `['a' => '…']` كما تقرأها `Question::grade()`.
     */
    public function fill(mixed $input): mixed
    {
        $rows = array_values(array_filter(
            (array) $input,
            fn ($row): bool => is_array($row) && filled($row['text'] ?? null),
        ));

        $options = [];

        foreach ($rows as $index => $row) {
            $key = filled($row['key'] ?? null) ? (string) $row['key'] : self::keyAt($index);
            $options[$key] = (string) $row['text'];
        }

        return $options;
    }

    /**
     * مفاتيح الإجابة الصحيحة — تُقرأ من المدخل نفسه ليُملأ عمود آخر.
     *
     * @return list<string>
     */
    public static function correctFrom(mixed $input): array
    {
        $rows = array_values(array_filter(
            (array) $input,
            fn ($row): bool => is_array($row) && filled($row['text'] ?? null),
        ));

        $correct = [];

        foreach ($rows as $index => $row) {
            if (! empty($row['correct'])) {
                $correct[] = filled($row['key'] ?? null) ? (string) $row['key'] : self::keyAt($index);
            }
        }

        return $correct;
    }

    /** a · b · c … ثم a1 لما يتجاوز الأبجدية — مفاتيح ثابتة لا أرقام صف. */
    public static function keyAt(int $index): string
    {
        $letters = range('a', 'z');

        return $index < count($letters)
            ? $letters[$index]
            : $letters[$index % count($letters)].(int) floor($index / count($letters));
    }
}
