<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Lms\Models\Certificate;
use Illuminate\View\View;

/**
 * صفحة التحقق العامة.
 *
 * تُفتح بلا تسجيل دخول: من يستلم شهادة من متقدّم لوظيفة يجب أن
 * يتحقق منها بكودها وحده. ولا تكشف أكثر من اللازم.
 */
final class CertificateController
{
    public function verify(string $code): View
    {
        $certificate = Certificate::with(['user', 'course'])->where('code', $code)->first();

        return view('lms.certificate', [
            'code' => $code,
            'certificate' => $certificate,
            'valid' => $certificate?->isValid() ?? false,
        ]);
    }
}
