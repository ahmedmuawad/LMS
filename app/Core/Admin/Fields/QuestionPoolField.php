<?php

declare(strict_types=1);

namespace App\Core\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

/**
 * وصفة الاختبار المولَّد: كم سؤالاً من كل مستوى، ومن أي تصنيف.
 *
 * كان عمود `question_pool` يقبل مستوًى واحداً، فالاختبار إمّا كلّه
 * سهل أو كلّه صعب. والامتحان الذي يُفرز الطلاب خلطة، فهذه الشاشة
 * تكتبها: خمسة سهلة وثلاث متوسطة واثنتان صعبتان.
 */
final class QuestionPoolField extends Field
{
    /** @var array<string, string> */
    private array $categories = [];

    /** @param  array<string, string>  $categories */
    public function categories(array $categories): self
    {
        $this->categories = $categories;

        return $this;
    }

    public function component(): string
    {
        return 'admin.fields.question-pool';
    }

    public function props(): array
    {
        return ['categories' => $this->categories];
    }

    public function validationRules(string $context): array
    {
        return ['nullable', 'array'];
    }

    public function valueFor(?Model $record): mixed
    {
        return (array) ($record === null ? [] : data_get($record, $this->name) ?? []);
    }

    public function fill(mixed $input): mixed
    {
        $input = (array) $input;

        $pool = [
            'easy' => max(0, (int) ($input['easy'] ?? 0)),
            'medium' => max(0, (int) ($input['medium'] ?? 0)),
            'hard' => max(0, (int) ($input['hard'] ?? 0)),
        ];

        if (filled($input['category_id'] ?? null)) {
            $pool['category_id'] = (int) $input['category_id'];
        }

        // بِركة بلا سؤال واحد ليست بِركة — تُحفظ فارغة فيعود الاختبار ثابتاً
        return array_sum([$pool['easy'], $pool['medium'], $pool['hard']]) === 0 ? null : $pool;
    }
}
