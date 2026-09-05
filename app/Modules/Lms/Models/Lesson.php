<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Core\Support\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class Lesson extends Model
{
    use HasTranslations;
    use TracksCreator;

    public const TYPES = [
        'video' => 'فيديو', 'audio' => 'صوت', 'text' => 'نص', 'pdf' => 'ملف PDF',
        'slides' => 'شرائح', 'live' => 'حصة مباشرة', 'scorm' => 'حزمة SCORM',
        'h5p' => 'محتوى تفاعلي (H5P)', 'embed' => 'تضمين',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'content'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'content' => 'array',
            'attachments' => 'array',
            'transcript' => 'array',
            'is_downloadable' => 'boolean',
        ];
    }

    public function items(): MorphMany
    {
        return $this->morphMany(CourseItem::class, 'itemable');
    }

    public function durationLabel(): string
    {
        $seconds = (int) $this->duration_seconds;

        if ($seconds < 60) {
            return trans_choice('{0} أقل من دقيقة|{1} ثانية|{2} ثانيتان|[3,10] :count ثوانٍ|[11,*] :count ثانية', $seconds, ['count' => $seconds]);
        }

        $minutes = (int) round($seconds / 60);

        return trans_choice('{1} دقيقة|{2} دقيقتان|[3,10] :count دقائق|[11,*] :count دقيقة', $minutes, ['count' => $minutes]);
    }
}
