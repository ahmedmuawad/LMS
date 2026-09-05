<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** تحدٍّ بمهلة: «أتمّ خمسة دروس هذا الأسبوع». */
final class Challenge extends Model
{
    use HasTranslations;

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'description'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(ChallengeCompletion::class);
    }

    /** الجارية الآن: مفعّلة، وبدأت، ولم تنتهِ */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /**
     * ما أنجزه الطالب من هذا التحدي.
     *
     * يُعدّ من `point_entries` داخل مدّة التحدي: هي سجلّ ما فعله
     * فعلاً، وعدّادٌ ثانٍ يفترق عنها يوماً ما.
     */
    public function progressFor(User $user): int
    {
        return (int) PointEntry::where('user_id', $user->getKey())
            ->where('rule', $this->rule)
            ->when($this->starts_at !== null, fn (Builder $q) => $q->where('created_at', '>=', $this->starts_at))
            ->when($this->ends_at !== null, fn (Builder $q) => $q->where('created_at', '<=', $this->ends_at))
            ->count();
    }

    /**
     * اسم القاعدة بالعربية.
     *
     * ومفتاح القاعدة فيه نقطة (`lesson.completed`)، و`config()`
     * تقسم عندها إلى مستويات — فتُقرأ المصفوفة كاملةً ويُفهرس منها.
     */
    public function ruleLabel(): string
    {
        $rules = (array) config('gamification.rules', []);

        return __((string) ($rules[$this->rule]['label'] ?? $this->rule));
    }

    /** المدّة الباقية — بصيغةٍ يقرؤها الطالب لا طابعاً زمنياً */
    public function endsInLabel(): ?string
    {
        return $this->ends_at?->isFuture()
            ? __('يبقى :when', ['when' => $this->ends_at->diffForHumans(null, true)])
            : ($this->ends_at === null ? null : __('انتهى'));
    }
}
