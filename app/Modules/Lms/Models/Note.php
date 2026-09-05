<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ملاحظة كتبها الطالب أثناء تعلّمه.
 *
 * `$guarded` هنا لا يكفي: الملاحظة تُنشأ من طلب الطالب مباشرة،
 * وتركُ `user_id` قابلاً للتعبئة الجماعية يعني أن يكتب أحدهم
 * ملاحظةً باسم غيره. فنُحدّد المسموح صراحةً.
 */
final class Note extends Model
{
    /** @var list<string> */
    protected $fillable = ['course_id', 'lesson_id', 'at_second', 'body', 'is_pinned'];

    protected function casts(): array
    {
        return [
            'at_second' => 'integer',
            'is_pinned' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** ملاحظات هذا المستخدم وحده — لا يُقرأ جدول الملاحظات بغير هذا النطاق */
    public function scopeOwnedBy(Builder $query, int|string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** موضع الملاحظة في الفيديو، مقروءاً: ٠٧:٤٢ */
    public function timestampLabel(): ?string
    {
        if ($this->at_second === null) {
            return null;
        }

        return sprintf('%02d:%02d', intdiv($this->at_second, 60), $this->at_second % 60);
    }
}
