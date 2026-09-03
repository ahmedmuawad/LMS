<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Modules\Lms\Models\Certificate;
use App\Modules\Lms\Models\CertificateTemplate;
use App\Modules\Lms\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/**
 * إصدار الشهادة.
 *
 * تُصدر مرة واحدة، وتُجمّد بياناتها وقت الإصدار: تغيير اسم الكورس
 * أو الطالب لاحقاً لا يجوز أن يغيّر شهادة بيد صاحبها ومعلَّقة على حائط.
 */
final class IssueCertificate
{
    public function handle(Enrollment $enrollment): ?Certificate
    {
        $course = $enrollment->course;

        if ($course === null || ! $course->certificate_enabled) {
            return null;
        }

        if (! setting('lms.auto_certificate', true)) {
            return null;
        }

        $threshold = (int) setting('lms.certificate_threshold', 100);

        if ($enrollment->progress_percent < $threshold) {
            return null;
        }

        $existing = Certificate::where('enrollment_id', $enrollment->getKey())->first();

        if ($existing !== null) {
            return $existing;
        }

        $months = (int) setting('lms.certificate_valid_months', 0);

        $certificate = Certificate::create([
            'code' => $this->code(),
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'enrollment_id' => $enrollment->getKey(),
            'template_id' => CertificateTemplate::where('is_default', true)->value('id'),
            'issued_at' => now(),
            'expires_at' => $months > 0 ? now()->addMonths($months) : null,
            'data' => [
                'student' => $enrollment->user?->name,
                'course' => $course->getTranslations('title'),
                'instructor' => $course->instructor?->name(),
                'grade' => $enrollment->grade,
                'completed_at' => $enrollment->completed_at?->toDateString(),
            ],
        ]);

        if ($enrollment->user !== null) {
            notify('lms.certificate_issued', $enrollment->user, [
                'course_title' => (string) $course->title,
                'certificate_code' => $certificate->code,
                'certificate_url' => url('/certificate/'.$certificate->code),
                'url' => url('/certificate/'.$certificate->code),
            ]);
        }

        return $certificate;
    }

    public function revoke(Certificate $certificate, string $reason): Certificate
    {
        $certificate->forceFill(['revoked_at' => now(), 'revoke_reason' => $reason])->save();

        return $certificate;
    }

    /**
     * كود فريد للتحقق العام. الصيغة من الإعدادات، والتسلسل داخل
     * معاملة بقفل — كودان متطابقان يعنيان شهادةً لا يمكن التحقق منها.
     */
    private function code(): string
    {
        $format = (string) setting('lms.certificate_code_format', 'CERT-{YEAR}-{SEQ}');
        $year = now()->format('Y');
        $prefix = str_replace(['{YEAR}', '{SEQ}'], [$year, ''], $format);

        return DB::transaction(function () use ($prefix): string {
            $last = Certificate::where('code', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('code')
                ->value('code');

            $sequence = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

            return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });
    }
}
