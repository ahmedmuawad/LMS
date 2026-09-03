<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $status
 */
final class Session extends Model
{
    public const STATUSES = [
        'scheduled' => 'مجدولة',
        'running' => 'جارية',
        'done' => 'انتهت',
        'cancelled' => 'ملغاة',
        'postponed' => 'مؤجّلة',
    ];

    protected $table = 'center_sessions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'attendance_taken_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'session_id');
    }

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['scheduled', 'running', 'done']);
    }

    public function attendanceTaken(): bool
    {
        return $this->attendance_taken_at !== null;
    }

    public function timeLabel(): string
    {
        return substr((string) $this->starts_at, 0, 5).' – '.substr((string) $this->ends_at, 0, 5);
    }

    /** @return array<string, int> عدد كل حالة حضور */
    public function attendanceSummary(): array
    {
        return $this->attendance()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }
}
