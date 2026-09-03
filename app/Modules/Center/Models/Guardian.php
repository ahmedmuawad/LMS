<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Guardian extends Model
{
    protected $table = 'center_guardians';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['can_login' => 'boolean', 'notification_prefs' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** ولي أمر واحد قد يتابع عدة أبناء — وهذا هو الشائع. */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student', 'guardian_id', 'student_id')
            ->withPivot('is_primary');
    }

    public function wants(string $event): bool
    {
        $prefs = $this->notification_prefs ?? [];

        return (bool) ($prefs[$event] ?? true);
    }
}
