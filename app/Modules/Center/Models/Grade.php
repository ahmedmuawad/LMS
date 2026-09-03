<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Grade extends Model
{
    use HasTranslations;

    protected $table = 'center_grades';

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return ['name' => 'array'];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
