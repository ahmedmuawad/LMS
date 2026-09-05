<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Lms\Models\Certificate;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\LessonProgress;
use App\Modules\Lms\Models\QuizAttempt;
use App\Modules\Lms\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * بياناتي: تصديرها وحذف حسابي.
 *
 * ## حقٌّ لا ميزة
 *
 * قانون حماية البيانات المصري (١٥١ لسنة ٢٠٢٠) وGDPR كلاهما يُلزم
 * بأمرين: أن يأخذ صاحب البيانات نسخته، وأن يطلب محوها. وغيابهما
 * ليس نقصَ ميزة — هو مخالفة.
 *
 * ## والحذف لا يمحو ما ليس ملكه وحده
 *
 * الطلب والفاتورة سجلٌّ محاسبي للمشترك يلزمه قانوناً، والدرجة
 * سجلٌّ أكاديمي قد يُسأل عنه. فتُجهَّل هويّته فيها ولا تُمحى:
 * يختفي الاسم والبريد والهاتف، ويبقى الرقم بلا صاحب.
 */
final class PrivacyController
{
    public function show(Request $request): View
    {
        return view('auth.privacy', ['user' => $this->user($request)]);
    }

    /**
     * تصدير كل ما عندنا عنه — JSON يقرؤه الإنسان والآلة.
     *
     * ولا يُرسَل بالبريد ولا يُجدوَل: الحجم صغير (سجلّ طالبٍ واحد)،
     * والانتظار يجعله يشكّ أنه لم يُرسَل فيطلبه خمس مرّات.
     */
    public function export(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $data = [
            'exported_at' => now()->toIso8601String(),
            'site' => site_name(),

            'account' => $user->only(['id', 'name', 'email', 'phone', 'role', 'status', 'created_at']),

            'enrollments' => Enrollment::where('user_id', $user->getKey())
                ->with('course:id,title,slug')
                ->get()
                ->map(fn (Enrollment $e): array => [
                    'course' => (string) $e->course?->title,
                    'status' => $e->status,
                    'progress' => $e->progress_percent,
                    'enrolled_at' => $e->created_at?->toIso8601String(),
                ]),

            'quiz_attempts' => QuizAttempt::whereIn('enrollment_id',
                Enrollment::where('user_id', $user->getKey())->pluck('id'))
                ->get(['quiz_id', 'score', 'max_score', 'percentage', 'passed', 'submitted_at']),

            'lesson_progress' => LessonProgress::whereIn('enrollment_id',
                Enrollment::where('user_id', $user->getKey())->pluck('id'))
                ->count(),

            'certificates' => Certificate::where('user_id', $user->getKey())
                ->get(['code', 'issued_at']),

            'orders' => Order::where('user_id', $user->getKey())
                ->get(['number', 'status', 'total_minor', 'currency', 'created_at']),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="my-data-'.$user->getKey().'.json"',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * حذف الحساب — بكلمة المرور، لا بضغطة.
     *
     * الحذف لا رجعة فيه، وزرٌّ يُضغط بالخطأ يُفقد الطالب كورساته
     * التي دفع فيها. فكلمةُ المرور هي التأكيد — وهي أصدق من نافذةٍ
     * تسأل «هل أنت متأكد؟».
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $request->validate([
            'password' => ['required', 'string'],
            'confirm' => ['accepted'],
        ], [], ['password' => __('كلمة المرور'), 'confirm' => __('التأكيد')]);

        if (! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return back()->withErrors(['password' => __('كلمة المرور غير صحيحة.')]);
        }

        /*
         | صاحب المنصّة لا يحذف نفسه من هنا.
         |
         | حسابه هو الوحيد الذي يفتح لوحتها؛ وحذفُه يترك منصّةً
         | بلا صاحب لا يفتحها إلا نحن.
         */
        if (in_array($user->role, ['owner', 'admin'], true)) {
            return back()->withErrors([
                'password' => __('حساب صاحب المنصّة لا يُحذف من هنا — تواصل معنا.'),
            ]);
        }

        DB::transaction(function () use ($user): void {
            $token = 'deleted-'.$user->getKey().'@'.parse_url((string) config('app.url'), PHP_URL_HOST);

            // يُجهَّل ولا يُمحى: السجلّ المحاسبي والأكاديمي يبقى بلا صاحب
            $user->forceFill([
                'name' => __('حسابٌ محذوف'),
                'email' => $token,
                'phone' => null,
                'password' => Hash::make(bin2hex(random_bytes(16))),
                'status' => 'deleted',
                'email_verified_at' => null,
                'remember_token' => null,
            ])->save();

            /*
             | وأجهزته تُنسى.
             |
             | من حذف حسابه لا يبقى له جهازٌ مسجَّل يفتح به — والقائمة
             | تحتفظ ببصماتٍ عنه بعد أن طلب المحو.
             */
            UserDevice::where('user_id', $user->getKey())->delete();
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(url('/'))->with('status', __('حُذف حسابك. وما يلزم حفظه قانونياً بقي بلا اسمك.'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        return $user;
    }
}
