<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Models\User;
use App\Modules\Lms\Jobs\ForwardXapiStatement;
use App\Modules\Lms\Models\XapiStatement;
use Illuminate\Support\Str;

/**
 * يحفظ عبارة xAPI واحدة.
 *
 * ## لماذا فعلٌ مشترك
 *
 * العبارات تصل من مصدرين: محتوى H5P داخل المنصة (بجلسة الطالب)،
 * وتطبيقاتٌ خارجية (بمفتاح واجهة برمجية). والقراءةُ واحدة — الفعل
 * والهدف والنتيجة — وما يفترق هو من يُصدّق على الفاعل.
 *
 * فلو نُسخ المنطق لمَوضعين لاختلف حقلٌ في أحدهما، ولأصبح تقريرٌ
 * واحد يعدّ مصدراً ولا يعدّ الآخر.
 */
final class RecordXapiStatement
{
    /**
     * @param  array<mixed>  $row  العبارة كما وصلت
     * @param  User|null  $actor  الفاعل حين نعرفه يقيناً (جلسةُ طالب)
     * @return string|null معرّف العبارة، أو null لما لا يصلح عبارةً
     */
    public function handle(array $row, ?User $actor = null): ?string
    {
        if (blank($row['verb']['id'] ?? null)) {
            return null;
        }

        $id = $this->uuid($row['id'] ?? null);

        XapiStatement::updateOrCreate(['id' => $id], [
            'user_id' => ($actor ?? $this->actorFromStatement($row))?->getKey(),
            'verb' => Str::afterLast((string) $row['verb']['id'], '/'),
            'object_id' => mb_substr((string) ($row['object']['id'] ?? ''), 0, 255),
            'object_name' => $this->firstString($row['object']['definition']['name'] ?? null),
            'result_score' => $this->score($row),
            'result_success' => $this->boolean($row['result']['success'] ?? null),
            'result_completion' => $this->boolean($row['result']['completion'] ?? null),
            'duration_seconds' => $this->duration($row['result']['duration'] ?? null),
            'statement' => $row,
            'stored_at' => now(),
        ]);

        /*
         | ثم تُنسخ إلى مخزن الجهة إن كان لها مخزن.
         |
         | بعد الحفظ لا قبله: لو أُرسلت أولاً وفشل الحفظ لبقيت
         | النتيجة عند غيرنا ولم تظهر في تقرير المدرّس.
         */
        if (filled(setting('integrations.lrs_endpoint'))) {
            ForwardXapiStatement::dispatch($row);
        }

        return $id;
    }

    /**
     * الفاعل حين لا نعرفه — يُطابَق بالبريد أو باسم الحساب.
     *
     * ولا يُنشأ حسابٌ لمن لم نجده: خادمٌ يرسل باسم بريدٍ مجهول لا
     * يصنع طالباً في منصّة مشتركٍ آخر. تُحفظ العبارة بلا صاحب،
     * ويبقى نصّها كاملاً لمن أراد مطابقتها يدوياً.
     *
     * @param  array<mixed>  $row
     */
    private function actorFromStatement(array $row): ?User
    {
        $mbox = (string) ($row['actor']['mbox'] ?? '');
        $email = Str::startsWith($mbox, 'mailto:') ? Str::after($mbox, 'mailto:') : null;

        $email ??= $row['actor']['account']['name'] ?? null;

        return filled($email) && is_string($email)
            ? User::where('email', $email)->first()
            : null;
    }

    private function uuid(mixed $given): string
    {
        return is_string($given) && Str::isUuid($given) ? $given : (string) Str::uuid();
    }

    /** @param array<mixed> $row */
    private function score(array $row): ?float
    {
        $raw = $row['result']['score']['raw'] ?? null;
        $max = $row['result']['score']['max'] ?? null;
        $scaled = $row['result']['score']['scaled'] ?? null;

        /*
         | الدرجة تُخزَّن نسبةً مئوية دائماً.
         |
         | H5P يرسل ٣ من ٥، وSCORM يرسل ٦٠، ونموذجٌ ثالث يرسل ٠٫٦.
         | وتقريرٌ يخلط الثلاثة كما وصلت لا يُقرأ. فيُوحَّد المقياس
         | عند الحفظ لا عند العرض — العرض في عشرة مواضع والحفظ في
         | موضعٍ واحد.
         */
        return match (true) {
            is_numeric($raw) && is_numeric($max) && (float) $max > 0 => round((float) $raw / (float) $max * 100, 2),
            is_numeric($scaled) => round((float) $scaled * 100, 2),
            is_numeric($raw) => (float) $raw,
            default => null,
        };
    }

    private function boolean(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    /** ISO 8601 duration: PT1H2M3S */
    private function duration(mixed $value): ?int
    {
        if (! is_string($value) || ! preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:([\d.]+)S)?$/', $value, $m)) {
            return null;
        }

        return (int) (((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60 + (float) ($m[3] ?? 0));
    }

    private function firstString(mixed $value): ?string
    {
        if (is_string($value)) {
            return mb_substr($value, 0, 255);
        }

        if (is_array($value) && $value !== []) {
            return mb_substr((string) reset($value), 0, 255);
        }

        return null;
    }
}
