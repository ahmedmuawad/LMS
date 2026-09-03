<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Attendance extends Model
{
    public const STATUSES = [
        'present' => 'حاضر',
        'absent' => 'غائب',
        'late' => 'متأخر',
        'excused' => 'بعذر',
        'online' => 'حضر أونلاين',
    ];

    public const METHODS = [
        'manual' => 'يدوي',
        'code' => 'كود الطالب',
        'qr' => 'مسح QR',
        'fingerprint' => 'بصمة',
        'nfc' => 'بطاقة NFC',
        'self' => 'تسجيل ذاتي',
        'meeting' => 'من الاجتماع',
    ];

    protected $table = 'center_attendance';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'guardian_notified' => 'boolean'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** الغياب بعذر ليس غياباً في نظر ولي الأمر ولا في نسبة الحضور. */
    public function countsAsAbsent(): bool
    {
        return $this->status === 'absent';
    }

    public function countsAsPresent(): bool
    {
        return in_array($this->status, ['present', 'late', 'online'], true);
    }
}
