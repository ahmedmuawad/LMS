<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** الحصة المتكررة: يوم من الأسبوع ووقت وقاعة. */
final class Schedule extends Model
{
    /** الأحد أول الأسبوع في مصر والخليج — لا الاثنين. */
    public const WEEKDAYS = [
        0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء',
        4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت',
    ];

    protected $table = 'center_schedules';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function weekdayLabel(): string
    {
        return __(self::WEEKDAYS[$this->weekday] ?? '—');
    }

    public function timeLabel(): string
    {
        return substr((string) $this->starts_at, 0, 5).' – '.substr((string) $this->ends_at, 0, 5);
    }
}
