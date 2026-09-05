<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

final class ScormPackage extends Model
{
    /** @var list<string> */
    protected $fillable = ['lesson_id', 'title', 'version', 'path', 'entry', 'size'];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function states(): HasMany
    {
        return $this->hasMany(ScormState::class, 'package_id');
    }

    /** رابط نقطة البداية داخل الحزمة */
    public function entryUrl(): string
    {
        return Storage::disk('public')->url($this->path.'/'.ltrim((string) $this->entry, '/'));
    }

    public function stateFor(User $user): ScormState
    {
        return $this->states()->firstOrCreate(['user_id' => $user->getKey()]);
    }

    public function sizeLabel(): string
    {
        $bytes = (int) $this->size;

        return $bytes >= 1_048_576
            ? number_format($bytes / 1_048_576, 1).' '.__('م.ب')
            : number_format($bytes / 1024).' '.__('ك.ب');
    }
}
