<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Page extends Model
{
    use HasTranslations;
    use SoftDeletes;

    public const STATUSES = ['draft' => 'مسودّة', 'published' => 'منشورة', 'scheduled' => 'مجدولة'];

    /** الصفحات الإلزامية: تُنشأ تلقائياً ولا تُحذف. */
    public const SYSTEM = [
        'about' => 'من نحن',
        'contact' => 'اتصل بنا',
        'terms' => 'الشروط والأحكام',
        'privacy' => 'سياسة الخصوصية',
        'refund' => 'سياسة الاسترداد',
        'faq' => 'الأسئلة الشائعة',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'excerpt'];

    protected function casts(): array
    {
        return [
            'title' => 'array', 'excerpt' => 'array', 'blocks' => 'array', 'seo' => 'array',
            'is_system' => 'boolean', 'published_at' => 'datetime',
        ];
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isLive(): bool
    {
        return $this->status === 'published'
            && ($this->published_at === null || $this->published_at->isPast());
    }
}
