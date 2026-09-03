<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Assessment extends Model
{
    use HasTranslations;

    public const TYPES = [
        'exam' => 'امتحان', 'quiz' => 'اختبار قصير', 'homework' => 'واجب',
        'oral' => 'شفوي', 'behaviour' => 'سلوك',
    ];

    protected $table = 'center_assessments';

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return ['name' => 'array', 'held_on' => 'date', 'max_marks' => 'float', 'weight' => 'float'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }
}
