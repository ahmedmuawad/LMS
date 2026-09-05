<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Api\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * مصادقة الواجهة البرمجية بمفتاح.
 *
 * ## لماذا لا جلسة
 *
 * الجلسة تصلح لمتصفّح؛ والتكامل يعمل من خادمٍ آخر بلا كوكيّات ولا
 * CSRF. والمفتاح في ترويسة `Authorization` هو ما تفهمه كل أداة.
 *
 * ## والرسائل لا تفرّق بين حالات الرفض
 *
 * مفتاحٌ غير موجود ومفتاحٌ منتهٍ ومفتاحٌ بلا نطاق: ثلاث رسائل
 * مختلفة تُخبر من يجرّب أيَّ نصفٍ أصاب. فالرفض واحد إلا في نقص
 * النطاق — وذلك يعرفه صاحب المفتاح أصلاً.
 */
final class AuthenticateApiToken
{
    /** أقصى طلبات في الدقيقة لكل مفتاح */
    private const PER_MINUTE = 120;

    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $plain = $this->tokenFrom($request);

        if ($plain === null) {
            return $this->deny(__('مفتاح الوصول مفقود أو غير صالح.'));
        }

        $token = ApiToken::match($plain);

        if ($token === null) {
            return $this->deny(__('مفتاح الوصول مفقود أو غير صالح.'));
        }

        /*
         | الحدّ على المفتاح لا على العنوان.
         |
         | التكاملات تعمل من خوادم سحابية تتشارك العناوين، فحدٌّ على
         | العنوان يعاقب مشتركاً بسبب جار له. والمفتاح هو الهوية.
         */
        $key = 'api:'.$token->getKey();

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            return response()->json([
                'message' => __('طلبات كثيرة. حاول بعد :n ثانية.', ['n' => RateLimiter::availableIn($key)]),
            ], 429, ['Retry-After' => RateLimiter::availableIn($key)]);
        }

        RateLimiter::hit($key, 60);

        if ($scope !== null && ! $token->allows($scope)) {
            return response()->json([
                'message' => __('هذا المفتاح لا يملك صلاحية :scope.', ['scope' => $scope]),
                'required_scope' => $scope,
            ], 403);
        }

        /*
         | آخر استعمال يُكتب بلا لمس `updated_at`.
         |
         | كل طلبٍ يكتب صفّاً، ورفعُ `updated_at` معه يجعل كل قراءةٍ
         | تبدو تعديلاً في أي سجلّ تدقيق.
         */
        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        Auth::setUser($token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }

    private function tokenFrom(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return trim(mb_substr($header, 7)) ?: null;
        }

        return null;
    }

    private function deny(string $message): Response
    {
        return response()->json(['message' => $message], 401, [
            'WWW-Authenticate' => 'Bearer',
        ]);
    }
}
