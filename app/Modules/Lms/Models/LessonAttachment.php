<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Modules\Content\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مرفق درس — ملفّ يُقرأ داخل المنصة لا رابطٌ يُنسَخ.
 */
final class LessonAttachment extends Model
{
    /** ما يُعرض داخل المتصفّح مباشرةً */
    public const VIEWABLE = ['application/pdf'];

    /** @var list<string> */
    protected $fillable = ['lesson_id', 'media_id', 'title', 'is_downloadable', 'watermark', 'position'];

    protected function casts(): array
    {
        return [
            'is_downloadable' => 'boolean',
            'watermark' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(AttachmentView::class, 'attachment_id');
    }

    public function name(): string
    {
        return $this->title ?: ($this->media?->name ?? __('مرفق'));
    }

    /** يُعرض داخل الصفحة، أم لا سبيل إلا التنزيل؟ */
    public function isViewable(): bool
    {
        return in_array((string) $this->media?->mime, self::VIEWABLE, true);
    }

    /**
     * ملفّات Word لا يعرضها متصفّح.
     *
     * فإن مُنع تنزيلها صارت مرفقاً لا سبيل إليه — وهذا أسوأ من عدم
     * رفعها. تُقال الحقيقة في الشاشة بدل زرٍّ لا يفعل شيئاً.
     */
    public function isUnreachable(): bool
    {
        return ! $this->isViewable() && ! $this->is_downloadable;
    }

    public function sizeLabel(): string
    {
        $bytes = (int) ($this->media?->size ?? 0);

        return match (true) {
            $bytes >= 1_048_576 => number_format($bytes / 1_048_576, 1).' '.__('م.ب'),
            $bytes >= 1024 => number_format($bytes / 1024).' '.__('ك.ب'),
            default => $bytes.' '.__('بايت'),
        };
    }

    public function kindLabel(): string
    {
        return match ((string) $this->media?->mime) {
            'application/pdf' => 'PDF',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word',
            default => __('ملف'),
        };
    }
}
