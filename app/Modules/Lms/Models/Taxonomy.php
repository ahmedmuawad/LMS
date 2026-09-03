<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** تصنيف موحّد: أقسام · مستويات · وسوم · تصنيفات بنك الأسئلة. */
final class Taxonomy extends Model
{
    use HasTranslations;

    public const TYPES = [
        'category' => 'قسم',
        'level' => 'مستوى',
        'tag' => 'وسم',
        'question_category' => 'تصنيف أسئلة',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
