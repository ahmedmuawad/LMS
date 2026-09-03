<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Core\Support\Concerns\TracksCreator;
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

    private function pooledQuestions(): Collection
    {
        $pool = $this->question_pool ?? [];

        return Question::query()
            ->when(filled($pool['category_id'] ?? null), fn ($q) => $q->where('category_id', $pool['category_id']))
            ->when(filled($pool['difficulty'] ?? null), fn ($q) => $q->where('difficulty', $pool['difficulty']))
            ->inRandomOrder()
            ->limit(max(1, (int) $this->questions_count))
            ->get();
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
