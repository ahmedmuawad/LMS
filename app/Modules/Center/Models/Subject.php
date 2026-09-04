<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Subject extends Model
{
    use HasTranslations;

    protected $table = 'center_subjects';

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return ['name' => 'array', 'is_active' => 'boolean'];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** مدرّسو هذه المادة — والسنتر فيه أكثر من واحد لكل مادة. */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'center_subject_teacher', 'subject_id', 'user_id')
            ->withPivot(['branch_id', 'share_percent', 'is_active'])
            ->wherePivot('is_active', true)
            ->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SubjectTeacher::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }
}
