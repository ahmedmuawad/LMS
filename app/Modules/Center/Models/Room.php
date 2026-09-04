<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Room extends Model
{
    use HasTranslations;

    protected $table = 'center_rooms';

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return ['name' => 'array', 'equipment' => 'array', 'is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function label(): string
    {
        return trim((string) $this->name.' — '.(string) ($this->branch?->name ?? ''), ' —');
    }
}
