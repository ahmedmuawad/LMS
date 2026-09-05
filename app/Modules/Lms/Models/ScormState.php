<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حالة طالبٍ في حزمة.
 *
 * `cmi` يحمل ما ترسله الحزمة كما هو — عشرات المفاتيح أكثرها لا
 * يُقرأ. والأربعة التي تُقرأ فعلاً مستخرَجةٌ في أعمدة، فالتقارير
 * لا تفكّ JSON لكل صفّ.
 */
final class ScormState extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cmi' => 'array',
            'score_raw' => 'float',
            'total_seconds' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ScormPackage::class, 'package_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** المعياران يستعملان ألفاظاً مختلفة لنفس المعنى */
    public function isComplete(): bool
    {
        return in_array($this->lesson_status, ['completed', 'passed'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->lesson_status) {
            'completed' => __('أتمّه'),
            'passed' => __('نجح'),
            'failed' => __('رسب'),
            'incomplete' => __('لم يُتمّه'),
            'browsed' => __('تصفّحه'),
            default => __('لم يبدأ'),
        };
    }

    public function timeLabel(): string
    {
        $s = (int) $this->total_seconds;

        return sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    }
}
