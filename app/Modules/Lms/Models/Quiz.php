<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Core\Support\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

final class Quiz extends Model
{
    use HasTranslations;
    use TracksCreator;

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'description'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'question_pool' => 'array',
            'shuffle_questions' => 'boolean',
            'shuffle_answers' => 'boolean',
            'negative_marking' => 'boolean',
        ];
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'quiz_question')
            ->withPivot(['position', 'marks_override'])
            ->orderBy('quiz_question.position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function items(): MorphMany
    {
        return $this->morphMany(CourseItem::class, 'itemable');
    }

    /**
     * أسئلة هذه المحاولة.
     *
     * الاختبار الديناميكي يسحب عشوائياً من بنك الأسئلة، فلا يرى
     * طالبان الورقة نفسها — وهذا أضعف ما يمكن فعله ضد الغش.
     */
    public function questionsForAttempt(): Collection
    {
        $questions = $this->type === 'dynamic'
            ? $this->pooledQuestions()
            : $this->questions()->get();

        return $this->shuffle_questions ? $questions->shuffle()->values() : $questions->values();
    }

    /**
     * سحب من البنك بخلطة صعوبات لا بمستوًى واحد.
     *
     * ورقة كلها سهل لا تُميّز طالباً عن طالب، وكلها صعب تُسقط الصفّ.
     * الامتحان الحقيقي خلطة: خمسة سهلة تُطمئن، وثلاث متوسطة تفرز،
     * واثنتان صعبتان تكشفان المتفوّق. فالبِركة تُوصف بعدد لكل مستوى.
     *
     * والنقص لا يُسكَت عنه: بنك فيه سؤالان صعبان وطُلب منه ثلاثة
     * يُعطي اثنين — وترك الفراغ خيرٌ من إبدال صعب بسهل خفيةً.
     */
    private function pooledQuestions(): Collection
    {
        $pool = $this->question_pool ?? [];

        $base = fn (): Builder => Question::query()
            ->when(filled($pool['category_id'] ?? null), fn ($q) => $q->where('category_id', $pool['category_id']))
            ->when(filled($pool['type'] ?? null), fn ($q) => $q->where('type', $pool['type']));

        $counts = array_filter([
            'easy' => (int) ($pool['easy'] ?? 0),
            'medium' => (int) ($pool['medium'] ?? 0),
            'hard' => (int) ($pool['hard'] ?? 0),
        ], fn (int $n): bool => $n > 0);

        // بلا خلطة معلَنة: السلوك القديم — عدد واحد بمستوى واحد أو بلا مستوى
        if ($counts === []) {
            return $base()
                ->when(filled($pool['difficulty'] ?? null), fn ($q) => $q->where('difficulty', $pool['difficulty']))
                ->inRandomOrder()
                ->limit(max(1, (int) $this->questions_count))
                ->get();
        }

        $questions = new Collection;

        foreach ($counts as $difficulty => $count) {
            $questions = $questions->concat(
                $base()->where('difficulty', $difficulty)->inRandomOrder()->limit($count)->get(),
            );
        }

        return $questions->values();
    }

    /**
     * ما تعِد به البِركة مقابل ما يقدر عليه البنك.
     *
     * تُعرض لمن يبني الاختبار قبل أن يُسنده لطلابه، لا بعد أن يشتكوا
     * من ورقة ناقصة.
     *
     * @return list<array{difficulty:string,wanted:int,available:int}>
     */
    public function poolShortfall(): array
    {
        $pool = $this->question_pool ?? [];
        $rows = [];

        foreach (['easy', 'medium', 'hard'] as $difficulty) {
            $wanted = (int) ($pool[$difficulty] ?? 0);

            if ($wanted <= 0) {
                continue;
            }

            $available = Question::query()
                ->when(filled($pool['category_id'] ?? null), fn ($q) => $q->where('category_id', $pool['category_id']))
                ->when(filled($pool['type'] ?? null), fn ($q) => $q->where('type', $pool['type']))
                ->where('difficulty', $difficulty)
                ->count();

            if ($available < $wanted) {
                $rows[] = compact('difficulty', 'wanted', 'available');
            }
        }

        return $rows;
    }

    public function marksFor(Question $question): float
    {
        return (float) ($question->pivot?->marks_override ?? $question->marks);
    }

    public function attemptsLeft(int $used): ?int
    {
        return $this->max_attempts === 0 ? null : max(0, $this->max_attempts - $used);
    }
}
