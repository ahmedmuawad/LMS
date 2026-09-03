<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $type
 * @property array|null $correct
 */
final class Question extends Model
{
    use HasTranslations;

    public const TYPES = [
        'single_choice' => 'اختيار واحد',
        'multiple_choice' => 'اختيار متعدّد',
        'true_false' => 'صح وخطأ',
        'match' => 'توصيل',
        'sort' => 'ترتيب',
        'dropdown' => 'قائمة منسدلة',
        'fill_blank' => 'أكمل الفراغ',
        'short_text' => 'إجابة قصيرة',
        'essay' => 'مقالي',
        'file_upload' => 'رفع ملف',
    ];

    /** ما لا تصحّحه الآلة — يبقى بانتظار المدرّس. */
    public const MANUAL_TYPES = ['essay', 'file_upload'];

    public const DIFFICULTIES = ['easy' => 'سهل', 'medium' => 'متوسط', 'hard' => 'صعب'];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'body', 'explanation'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'body' => 'array',
            'explanation' => 'array',
            'options' => 'array',
            'correct' => 'array',
            'marks' => 'float',
            'negative_marks' => 'float',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'category_id');
    }

    public function needsHumanGrading(): bool
    {
        return in_array($this->type, self::MANUAL_TYPES, true);
    }

    /**
     * تصحيح آلي. يُعيد null لما يحتاج بشراً.
     *
     * @param  mixed  $answer  إجابة الطالب كما وصلت
     */
    public function grade(mixed $answer): ?bool
    {
        if ($this->needsHumanGrading()) {
            return null;
        }

        $correct = $this->correct ?? [];

        return match ($this->type) {
            'single_choice', 'true_false', 'dropdown' => (string) $answer === (string) ($correct[0] ?? ''),

            // الاختيار المتعدّد: الإجابة صحيحة بالمجموعة كاملةً لا بجزء منها
            'multiple_choice' => $this->sameSet((array) $answer, $correct),

            // الترتيب: التسلسل نفسه بالضبط
            'sort' => array_values((array) $answer) === array_values($correct),

            // التوصيل: كل زوج في مكانه
            'match' => $this->sameMap((array) $answer, $correct),

            // النص: نقارن بعد تطبيع المسافات وتشكيل العربية
            'fill_blank', 'short_text' => $this->matchesText((string) $answer, $correct),

            default => null,
        };
    }

    /** @param  array<int|string, mixed>  $correct */
    private function sameSet(array $answer, array $correct): bool
    {
        $a = array_map('strval', array_values($answer));
        $b = array_map('strval', array_values($correct));
        sort($a);
        sort($b);

        return $a === $b;
    }

    /** @param  array<int|string, mixed>  $correct */
    private function sameMap(array $answer, array $correct): bool
    {
        ksort($answer);
        ksort($correct);

        return array_map('strval', $answer) === array_map('strval', $correct);
    }

    /** @param  array<int|string, mixed>  $correct */
    private function matchesText(string $answer, array $correct): bool
    {
        $normalised = self::normalise($answer);

        foreach ($correct as $accepted) {
            if (self::normalise((string) $accepted) === $normalised) {
                return true;
            }
        }

        return false;
    }

    /**
     * تطبيع للمقارنة: تُزال الحركات، وتُوحَّد الألف والياء والتاء
     * المربوطة. الطالب الذي كتب «مصطفي» بدل «مصطفى» أجاب صحيحاً.
     */
    public static function normalise(string $text): string
    {
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text) ?? $text;
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي']);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_strtolower(trim($text));
    }
}
