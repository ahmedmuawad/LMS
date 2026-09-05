<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** مسار: رحلةٌ عبر كورسات بترتيب. */
final class LearningPath extends Model
{
    use HasTranslations;

    public const STATUSES = ['draft' => 'مسودّة', 'published' => 'منشور', 'archived' => 'مؤرشف'];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'description'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'is_public' => 'boolean',
            'is_sequential' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(LearningPathItem::class, 'path_id')->orderBy('position');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'learning_path_items', 'path_id', 'course_id')
            ->withPivot(['position', 'is_required'])
            ->orderBy('learning_path_items.position');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(LearningPathEnrollment::class, 'path_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * تقدّم الطالب: نسبةُ ما أتمّه من كورسات المسار.
     *
     * ويُحسب من تسجيلاته في الكورسات لا من عدّادٍ في المسار: الطالب
     * قد يُتمّ كورساً قبل أن يدخل المسار أصلاً، وعدّادٌ مستقل لا
     * يعرف ذلك.
     */
    public function progressFor(User $user): int
    {
        $courseIds = $this->items()->where('is_required', true)->pluck('course_id');

        if ($courseIds->isEmpty()) {
            return 0;
        }

        $done = Enrollment::where('user_id', $user->getKey())
            ->whereIn('course_id', $courseIds)
            ->where('status', 'completed')
            ->count();

        return (int) round($done / $courseIds->count() * 100);
    }

    /**
     * الكورس التالي في الرحلة — ما لم يُتمّه بعد.
     *
     * وهو ما يحتاجه الطالب: لا قائمةً يختار منها، بل «ابدأ من هنا».
     */
    public function nextCourseFor(User $user): ?Course
    {
        $done = Enrollment::where('user_id', $user->getKey())
            ->where('status', 'completed')
            ->pluck('course_id')
            ->all();

        foreach ($this->items()->with('course')->get() as $item) {
            if ($item->course !== null && ! in_array($item->course_id, $done, true)) {
                return $item->course;
            }
        }

        return null;
    }

    /**
     * هل يُفتح هذا الكورس للطالب؟
     *
     * في المسار المتسلسل لا يُفتح كورسٌ حتى يُتمّ ما قبله. والقيد
     * على الترتيب لا على الشراء: من اشترى المسار كلّه يمشي فيه
     * بترتيبه.
     */
    public function unlocks(User $user, Course $course): bool
    {
        if (! $this->is_sequential) {
            return true;
        }

        $done = Enrollment::where('user_id', $user->getKey())
            ->where('status', 'completed')
            ->pluck('course_id')
            ->all();

        foreach ($this->items()->get() as $item) {
            if ($item->course_id === $course->getKey()) {
                return true;
            }

            if ($item->is_required && ! in_array($item->course_id, $done, true)) {
                return false;
            }
        }

        return true;
    }
}
