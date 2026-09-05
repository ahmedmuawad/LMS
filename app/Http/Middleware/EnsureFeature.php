<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يمنع الوصول إلى ميزة خارج باقة المشترك.
 *
 * كان القفل في القائمة الجانبية وحدها: العنصر يظهر مقفولاً، ومساره
 * يُفتح بكتابته في شريط العنوان. قفلٌ بصريّ لا أمنيّ — ومن يعرف
 * الرابط لا يدفع.
 *
 * يُمرَّر مفتاح أو أكثر؛ ويكفي واحد ليُسمح — كالصلاحيات، فالشاشة
 * الواحدة قد تخدم ميزتين.
 */
final class EnsureFeature
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $tenant = tenant();

        // خارج سياق مشترك لا باقة تُفحص — والمسارات المركزية لها حراسها
        if ($tenant === null || $features === []) {
            return $next($request);
        }

        foreach ($features as $feature) {
            if ($tenant->allows($feature)) {
                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('هذه الميزة غير متاحة في باقتك.'),
                'features' => $features,
                'upgrade_url' => url('/admin/billing'),
            ], 402);
        }

        /*
         | ٤٠٢ لا ٤٠٣: المنع مالي لا صلاحيّاتي، والفرق يهمّ من يقرأ
         | السجلّ — و٤٠٣ تجعل صاحب الحساب يظنّ أنه لا يملك الإذن في
         | منصّته هو.
         */
        return response()->view('errors.feature-locked', [
            'features' => $features,
        ], 402);
    }
}
