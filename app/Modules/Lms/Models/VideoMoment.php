<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نقطة تفاعل داخل فيديو درس.
 */
final class VideoMoment extends Model
{
    public const KINDS = [
        'question' => 'سؤال',
        'note' => 'ملاحظة',
        'link' => 'رابط',
    ];

    /** @var list<string> */
    protected $fillable = ['lesson_id', 'at_second', 'kind', 'question_id', 'body', 'url', 'is_required'];

    protected function casts(): array
    {
        return [
            'at_second' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(MomentResponse::class, 'moment_id');
    }

    public function atLabel(): string
    {
        $s = (int) $this->at_second;

        return sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    }

    /** إجابة هذا المستخدم إن أجاب — تمنع تكرار السؤال عليه */
    public function responseOf(User $user): ?MomentResponse
    {
        return $this->responses()->where('user_id', $user->getKey())->first();
    }
}
