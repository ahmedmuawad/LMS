<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ملف الطالب في السنتر — امتداد لحسابه لا بديل عنه.
 * سجلٌّ واحد للطالب يجمع حضوره وأقساطه ودرجاته وكورساته المسجّلة.
 */
final class Student extends Model
{
    protected $table = 'center_students';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'joined_at' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student', 'student_id', 'guardian_id')
            ->withPivot('is_primary');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CenterEnrollment::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function name(): string
    {
        return (string) ($this->user?->name ?? __('طالب'));
    }

    /**
     * كود الكارنيه — يُقرأ ويُكتب بيد إنسان ويُمسح بالـ QR.
     * بلا حروف تلتبس بأرقام، فالكود يُملى صوتاً في الطابور.
     */
    public static function nextCode(): string
    {
        $prefix = 'ST';
        $last = self::where('code', 'like', $prefix.'%')->orderByDesc('code')->value('code');
        $sequence = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
