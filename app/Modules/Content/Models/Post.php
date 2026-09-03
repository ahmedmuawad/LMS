<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Models\User;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Post extends Model
{
    use HasTranslations;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'مسودّة', 'pending' => 'بانتظار المراجعة',
        'published' => 'منشور', 'scheduled' => 'مجدول',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'excerpt', 'body'];

    protected function casts(): array
    {
        return [
            'title' => 'array', 'excerpt' => 'array', 'body' => 'array',
            'blocks' => 'array', 'seo' => 'array',
            'allow_comments' => 'boolean', 'featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Taxonomy::class, 'post_tag', 'post_id', 'taxonomy_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /**
     * وقت القراءة يُحسب من النص لا يُكتب يدوياً.
     * ٢٠٠ كلمة في الدقيقة تقدير معقول للعربية والإنجليزية معاً.
     */
    public function estimateReadingMinutes(): int
    {
        $words = 0;

        foreach ($this->getTranslations('body') as $text) {
            $words = max($words, str_word_count(strip_tags((string) $text)) ?: mb_strlen((string) $text) / 6);
        }

        return max(1, (int) ceil($words / 200));
    }
}
