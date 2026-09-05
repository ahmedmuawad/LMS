<?php

declare(strict_types=1);

namespace App\Http\Controllers\Center;

use App\Core\Access\Ability;
use App\Modules\Center\Actions\SelfCheckIn;
use App\Modules\Center\Models\Session;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * الكود المتغيّر على شاشة القاعة، ومسحُه من هاتف الطالب.
 *
 * ## شاشتان لا واحدة
 *
 * شاشةُ العرض تُفتح على تلفاز القاعة أو لابتوب المدرّس ولا تُلمس؛
 * وشاشةُ المسح تُفتح في هاتف الطالب بعد أن يصوّب كاميرته. وخلطُهما
 * يعني كوداً يعرضه الطالب لنفسه.
 */
final class SelfCheckInController
{
    public function __construct(private readonly SelfCheckIn $checkIn) {}

    /** شاشة العرض — تُفتح على تلفاز القاعة */
    public function show(Request $request, string $sessionId): View
    {
        $this->authorise($request);

        $session = Session::with(['group.subject', 'room'])->findOrFail($sessionId);

        return view('center.checkin-display', ['session' => $session]);
    }

    /**
     * الكود الحالي — تطلبه الشاشة كلّما انتهى الذي قبله.
     *
     * ويُعاد رسمُه في الخادم لا في المتصفّح: مكتبة رسمٍ في الواجهة
     * تعني ملفّاً ثقيلاً يُحمَّل في كل قاعة لأجل صورةٍ واحدة.
     */
    public function token(Request $request, string $sessionId): JsonResponse
    {
        $this->authorise($request);

        $session = Session::findOrFail($sessionId);

        $token = $this->checkIn->token($session);

        return response()->json([
            'svg' => $this->qr(route('center.checkin.scan', ['token' => $token])),
            'expires_in' => $this->checkIn->expiresIn(),

            /*
             | والكود نصّاً أيضاً.
             |
             | كاميرا هاتفٍ قديم قد لا تقرأ الرمز، وطالبٌ واقفٌ بلا
             | حيلة يجعل المدرّس يترك النظام كلّه ويعود إلى الورق.
             */
            'code' => mb_strtoupper(mb_substr(explode('.', $token)[2], 0, 6)),
        ]);
    }

    /** يفتحها الطالب بعد المسح */
    public function scan(Request $request, string $token): View
    {
        $user = $request->user();

        abort_if($user === null, 403);

        try {
            $result = $this->checkIn->handle($token, $user);
        } catch (RuntimeException $e) {
            return view('center.checkin-result', [
                'ok' => false,
                'message' => $e->getMessage(),
                'session' => null,
            ]);
        }

        return view('center.checkin-result', [
            'ok' => true,
            'message' => $result['status'] === 'late'
                ? __('سُجِّل حضورك — متأخّراً.')
                : __('سُجِّل حضورك.'),
            'session' => $result['session']->loadMissing('group.subject'),
        ]);
    }

    private function qr(string $data): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(320, 1), new SvgImageBackEnd));

        return $writer->writeString($data);
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::ATTENDANCE_TAKE), 403);
    }
}
