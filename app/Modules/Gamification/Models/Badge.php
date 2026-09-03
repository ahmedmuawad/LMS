<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Badge extends Model
{
    use HasTranslations;

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'badge_user')->withPivot('awarded_at');
    }
}
