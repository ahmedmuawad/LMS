<?php

declare(strict_types=1);

namespace App\Http\Controllers\Center;

use App\Core\Access\Ability;
use App\Modules\Center\Actions\RecordPunch;
use App\Modules\Center\Models\AttendanceDevice;
use App\Modules\Center\Models\Branch;
use App\Modules\Center\Models\DevicePunch;
use App\Modules\Center\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * أجهزة الحضور: إدارتها، ونقطة استقبال بصماتها.
 *
 * السنتر الذي يشتري جهاز بصمة يريده أن يكتب في المنصة مباشرةً، لا
 * أن يُصدَّر ملفّ Excel كل ليلة ويُرفَع يدوياً.
 *
 * وأجهزة السوق المصري عشرات الطُّرُز ببروتوكولات مغلقة ومتفاوتة،
 * فربطُ طرازٍ بعينه يخدم من اشتراه ويترك البقية. والباب هنا واحد
 * بسيط: «هذا الكود حضر الآن» — وهذا ما يستطيعه كل جهاز، مباشرةً
 * أو بسكربتٍ صغير يقرأ سجلّه ويُرسل.
 */
final class DeviceController
{
    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('center.devices', [
            'devices' => AttendanceDevice::with(['branch', 'room'])->latest('id')->get(),
            'punches' => DevicePunch::with(['device', 'session.group'])
                ->latest('id')->limit(50)->get(),
            'branches' => Branch::where('is_active', true)->get(),
            'rooms' => Room::where('is_active', true)->get(),
            'kinds' => AttendanceDevice::KINDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorise($request);

        $input = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceDevice::KINDS))],
            'branch_id' => ['nullable', 'integer', 'exists:center_branches,id'],
            'room_id' => ['nullable', 'integer', 'exists:center_rooms,id'],
        ]);

        ['plain' => $plain] = AttendanceDevice::register(
            $input['name'],
            $input['kind'],
            $input['branch_id'] ?? null,
            $input['room_id'] ?? null,
        );

        return back()
            ->with('status', __('سُجّل الجهاز. انسخ مفتاحه الآن — لن يُعرض مرة أخرى.'))
            ->with('device_token', $plain);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->authorise($request);

        AttendanceDevice::findOrFail($id)->delete();

        return back()->with('status', __('حُذف الجهاز. لن تُقبل بصماته بعد الآن.'));
    }

    /**
     * نقطة استقبال البصمة.
     *
     * تُنادى من الجهاز أو من وسيطٍ صغير عنده. وترجع دائماً بجسمٍ
     * مقروء: الجهاز البسيط يعرض نصّاً على شاشته الصغيرة، والوسيط
     * يقرأ `result` ليعرف ماذا يفعل.
     */
    public function punch(Request $request, RecordPunch $action): JsonResponse
    {
        $device = AttendanceDevice::match((string) $request->bearerToken());

        if ($device === null) {
            return response()->json(['message' => __('مفتاح الجهاز غير صالح.')], 401);
        }

        $input = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'at' => ['nullable', 'date'],
        ]);

        /*
         | وقت الجهاز يُقبل ويُقيَّد.
         |
         | الأجهزة تعمل بلا إنترنت أحياناً وترسل دفعةً حين يعود، فوقتها
         | هو الصحيح لا وقت الوصول. لكن ساعةً خاطئة في جهاز تسجّل
         | حضوراً في الشهر الماضي — فيُقبل ما بين يومٍ مضى وساعةٍ آتية.
         */
        $at = isset($input['at']) ? Carbon::parse($input['at']) : now();

        if ($at->lessThan(now()->subDay()) || $at->greaterThan(now()->addHour())) {
            $at = now();
        }

        $result = $action->handle($device, $input['code'], $at);

        return response()->json([
            'result' => $result['result'],
            'message' => __(DevicePunch::RESULTS[$result['result']] ?? $result['result']),
            'student' => $result['student'],
        ], $result['result'] === 'matched' ? 200 : 202);
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::ATTENDANCE_TAKE), 403);

        abort_unless(
            tenant()?->allows('attendance_devices') ?? false,
            402,
            __('أجهزة الحضور غير متاحة في باقتك.'),
        );
    }
}
