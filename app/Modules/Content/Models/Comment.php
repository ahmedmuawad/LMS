<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Comment extends Model
{
    public const STATUSES = [
        'pending' => 'بانتظار المراجعة', 'approved' => 'منشور',
        'spam' => 'سبام', 'trash' => 'محذوف',
    ];

    protected $guarded = [];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->where('status', 'approved');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function authorName(): string
    {
        return (string) ($this->user?->name ?? $this->author_name ?? __('زائر'));
    }
}
