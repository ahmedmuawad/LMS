<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إجابة طالب على نقطة تفاعل.
 *
 * `$fillable` صريح: السطر يُنشأ من طلب الطالب، وترك `user_id`
 * قابلاً للتعبئة الجماعية يعني أن يُجيب أحدهم باسم غيره.
 */
final class MomentResponse extends Model
{
    /** @var list<string> */
    protected $fillable = ['moment_id', 'answer', 'is_correct'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function moment(): BelongsTo
    {
        return $this->belongsTo(VideoMoment::class, 'moment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
