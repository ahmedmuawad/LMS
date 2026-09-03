<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Stage extends Model
{
    use HasTranslations;

    protected $table = 'center_stages';

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return ['name' => 'array'];
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class)->orderBy('position');
    }
}
