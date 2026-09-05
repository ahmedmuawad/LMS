<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عبارة xAPI: «فلانٌ فعل كذا بنتيجة كذا».
 *
 * المفتاح UUID لا رقم متسلسل: المعيار يجعل المُرسِل يختار المعرّف،
 * وإعادةُ إرسال العبارة نفسها يجب ألّا تُنشئ صفّاً ثانياً.
 */
final class XapiStatement extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'statement' => 'array',
            'result_score' => 'float',
            'result_success' => 'boolean',
            'result_completion' => 'boolean',
            'stored_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** الفعل مقروءاً بالعربية حيث نعرفه */
    public function verbLabel(): string
    {
        return match ($this->verb) {
            'completed' => __('أتمّ'),
            'passed' => __('نجح'),
            'failed' => __('رسب'),
            'answered' => __('أجاب'),
            'attempted' => __('حاول'),
            'experienced' => __('شاهد'),
            'progressed' => __('تقدّم'),
            'interacted' => __('تفاعل'),
            default => $this->verb,
        };
    }
}
