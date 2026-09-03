<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * مكتبة الوسائط.
 *
 * البصمة تمنع رفع الملف نفسه مرتين: مساحة المشترك محدودة بباقته،
 * ولا معنى لأن يدفع ثمن نسخة مكرّرة من شعاره.
 */
final class Media extends Model
{
    use HasTranslations;

    protected $table = 'media';

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['alt'];

    protected function casts(): array
    {
        return ['alt' => 'array', 'conversions' => 'array'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('mime', 'like', 'image/%');
    }

    public function url(?string $conversion = null): string
    {
        $path = $conversion === null
            ? $this->path
            : ($this->conversions[$conversion] ?? $this->path);

        return Storage::disk($this->disk)->url($path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0).' KB',
            default => $bytes.' B',
        };
    }
}
