<?php

declare(strict_types=1);

namespace App\Modules\Lms\Models;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** رمزٌ يُطبَع على الورق ويفتح درساً أو اختباراً. */
final class PrintCode extends Model
{
    public const TARGETS = [
        'lesson' => 'درس',
        'quiz' => 'اختبار',
        'course' => 'كورس',
        'url' => 'رابط خارجي',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_scan_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * رمزٌ قصير يُقرأ ويُكتب.
     *
     * بلا الأحرف المتشابهة (0/O و1/l): الطالب قد يكتبه بيده حين
     * لا تعمل الكاميرا، وخطأٌ في حرفٍ واحد يفتح رمز غيره.
     */
    public static function freshCode(): string
    {
        do {
            $code = Str::upper(Str::substr(
                str_replace(['0', 'O', '1', 'L', 'I'], '', Str::random(24)),
                0,
                7,
            ));
        } while ($code === '' || self::where('code', $code)->exists());

        return $code;
    }

    /**
     * رابط الرمز — على نطاق المشترك دائماً.
     *
     * `url()` تقرأ نطاق الطلب، ولا طلبَ في أمر سطر أوامر ولا في
     * طابور — فتُعطي النطاق المركزي. والرمز يُطبَع على ورق: رابطٌ
     * خاطئ فيه لا يُصلَح بعد الطباعة، فيُبنى من نطاق المشترك نفسه.
     */
    public function url(): string
    {
        $domain = tenant()?->domains?->firstWhere('is_primary', true)?->domain
            ?? tenant()?->domains?->first()?->domain;

        if ($domain === null || request()?->getHost() === $domain) {
            return url('/q/'.$this->code);
        }

        return 'https://'.$domain.'/q/'.$this->code;
    }

    /** الوجهة التي يُحوَّل إليها الماسح */
    public function destination(): ?string
    {
        return match ($this->target_type) {
            'url' => filled($this->target_url) ? (string) $this->target_url : null,
            'course' => ($slug = Course::where('id', $this->target_id)->value('slug'))
                ? $this->tenantUrl('/courses/'.$slug)
                : null,
            'lesson', 'quiz' => $this->learnUrl(),
            default => null,
        };
    }

    public function targetLabel(): string
    {
        return __(self::TARGETS[$this->target_type] ?? $this->target_type);
    }

    /** الرمز صورةً — يُدرَج في صفحة الطباعة مباشرةً بلا ملفّ */
    public function svg(int $size = 160): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle($size, 0), new SvgImageBackEnd)))
            ->writeString($this->url());
    }

    /**
     * الدرس والاختبار يُفتحان داخل غرفة التعلّم لا مفردَين.
     *
     * الطالب يحتاج ما حولهما: منهج الكورس وتقدّمه وزرّ «التالي».
     * وفتحُهما مفردَين يُخرجه من سياقه فيضيع.
     */
    private function learnUrl(): ?string
    {
        $class = $this->target_type === 'quiz' ? Quiz::class : Lesson::class;

        $item = CourseItem::where('itemable_type', $class)
            ->where('itemable_id', $this->target_id)
            ->with('course')
            ->first();

        return $item?->course === null
            ? null
            : $this->tenantUrl('/learn/'.$item->course->slug.'/'.$item->getKey());
    }

    /** عنوانٌ على نطاق المشترك — انظر [url] */
    private function tenantUrl(string $path): string
    {
        $domain = tenant()?->domains?->firstWhere('is_primary', true)?->domain
            ?? tenant()?->domains?->first()?->domain;

        return $domain === null || request()?->getHost() === $domain
            ? url($path)
            : 'https://'.$domain.$path;
    }
}
