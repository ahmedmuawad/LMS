<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Modules\Lms\Models\UserDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * أجهزة الحساب — يراها صاحبه ويفكّ ما لم يعد يستعمله.
 *
 * الحدّ بلا شاشةٍ لفكّه سجنٌ: من بدّل هاتفه يجد حسابه مقفولاً بلا
 * مخرج إلا الدعم. والشاشة هي التي تحوّل الحدّ من عقوبة إلى قاعدة.
 */
final class DeviceController
{
    public function destroy(Request $request, int $id): RedirectResponse
    {
        // النطاق قبل البحث: جهاز غيرك لا يُفكّ بمعرّفٍ من الطلب
        $device = UserDevice::where('user_id', $request->user()->getKey())->findOrFail($id);

        $device->delete();

        return back()->with('status', __('فُصل الجهاز. لن يدخل به أحد إلا بتسجيل دخول جديد.'));
    }
}
