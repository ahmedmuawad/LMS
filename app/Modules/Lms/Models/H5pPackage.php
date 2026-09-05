<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class H5pPackage extends Model
{
    /** @var list<string> */
    protected $fillable = ['lesson_id', 'title', 'main_library', 'path', 'size'];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * نتيجة كل طالب: آخر درجةٍ سجّلها في الحزمة نفسها.
     *
     * المشغّل يُبلّغ عن كل تفاعلٍ بهدفٍ يبدأ بمعرّف الحزمة
     * (`h5p:12` لها، و`h5p:12/ab3f…` لسؤالٍ داخلها). والملخّص يقرأ
     * الحزمة وحدها: الأسئلة الداخلية تجعل طالباً واحداً عشرة صفوف
     * لا يُعرف أيّها نتيجته.
     *
     * @return Collection<int, XapiStatement>
     */
    public function results(): Collection
    {
        return XapiStatement::query()
            ->where('object_id', $this->objectId())
            ->whereNotNull('user_id')
            ->with('user')
            ->orderByDesc('stored_at')
            ->get()
            ->unique('user_id')
            ->values();
    }

    /** معرّف الهدف الذي تحمله عبارات هذه الحزمة */
    public function objectId(): string
    {
        return 'h5p:'.$this->getKey();
    }

    /** مجلّد الحزمة كما يراه المتصفّح — المشغّل يقرأ منه h5p.json */
    public function folderUrl(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function sizeLabel(): string
    {
        $bytes = (int) $this->size;

        return $bytes >= 1_048_576
            ? number_format($bytes / 1_048_576, 1).' '.__('م.ب')
            : number_format($bytes / 1024).' '.__('ك.ب');
    }

    /** اسم نوع المحتوى بلا بادئة `H5P.` — الطالب لا يعنيه المكان في الشجرة */
    public function kindLabel(): ?string
    {
        if (blank($this->main_library)) {
            return null;
        }

        return trim(str_replace('H5P.', '', (string) $this->main_library));
    }
}
