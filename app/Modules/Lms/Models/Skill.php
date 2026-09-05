<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** مهارة تُقاس بأسئلة البنك. */
final class Skill extends Model
{
    use HasTranslations;

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_skill');
    }
}
