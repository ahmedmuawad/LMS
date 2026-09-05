<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Access\Ability;
use App\Modules\Whatsapp\EvolutionApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * ربط رقم المشترك بواتساب — بمسح رمزٍ من هاتفه.
 *
 * ## الرمز يُطلَب ولا يُحفَظ
 *
 * رمز الاقتران ينتهي بعد دقيقةٍ تقريباً، فحفظُه يجعل من يفتح الشاشة
 * غداً يمسح رمزاً ميتاً ويظنّ الربط معطّلاً. فيُطلَب طازجاً عند كل
 * فتح، وتُعاد النسخة نفسها لا نسخةٌ ثانية.
 */
final class WhatsAppLinkController
{
    public function __construct(private readonly EvolutionApi $api) {}

    public function show(Request $request): View
    {
        $this->authorise($request);

        $tenant = tenant();
        $instance = (string) ($tenant?->wa_instance ?? '');

        $state = 'unset';
        $number = $tenant?->wa_number;

        if ($instance !== '' && $this->api->configured()) {
            try {
                $state = $this->api->state($instance);
            } catch (Throwable) {
                $state = 'unknown';
            }
        }

        return view('admin.whatsapp', [
            'ready' => $this->api->configured(),
            'instance' => $instance,
            'state' => $state,
            'number' => $number,
        ]);
    }

    /**
     * ينشئ النسخة (أو يُعيد رمزاً لنسخةٍ قائمة) ويعيد الرمز صورةً.
     */
    public function connect(Request $request): JsonResponse
    {
        $this->authorise($request);

        $tenant = tenant();

        abort_if($tenant === null, 404);

        if (! $this->api->configured()) {
            return response()->json(['message' => __('لم تُضبط بوّابة واتساب بعد — راجع إدارة المنصّة.')], 422);
        }

        $instance = (string) ($tenant->wa_instance ?? '');

        try {
            if ($instance === '') {
                $instance = $this->api->instanceNameFor((string) $tenant->getTenantKey(), (string) $tenant->slug);
                $made = $this->api->createInstance($instance);

                $tenant->forceFill([
                    'wa_instance' => $instance,
                    'wa_token' => $made['token'],
                ])->save();

                return response()->json([
                    'qr' => $made['qr'],
                    'pairing' => $made['pairing'],
                    'state' => 'connecting',
                ]);
            }

            // نسخةٌ قائمة: إن كانت موصولةً فلا رمز، وإلّا رمزٌ جديد
            if ($this->api->state($instance) === 'open') {
                return response()->json(['state' => 'open', 'qr' => null]);
            }

            $fresh = $this->api->connect($instance);

            return response()->json([
                'qr' => $fresh['qr'],
                'pairing' => $fresh['pairing'],
                'state' => 'connecting',
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** تُنادى من الشاشة كل ثوانٍ حتى يتّصل — أو يمَلّ */
    public function state(Request $request): JsonResponse
    {
        $this->authorise($request);

        $tenant = tenant();
        $instance = (string) ($tenant?->wa_instance ?? '');

        if ($instance === '' || ! $this->api->configured()) {
            return response()->json(['state' => 'unset']);
        }

        $state = $this->api->state($instance);

        // الرقم يُحفظ عند أول اتصال: يراه المشترك فيتأكّد أنه ربط الصحيح
        if ($state === 'open' && blank($tenant->wa_number)) {
            $tenant->forceFill(['wa_number' => $this->api->number($instance)])->save();
        }

        return response()->json([
            'state' => $state,
            'number' => $tenant->fresh()->wa_number,
        ]);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->authorise($request);

        $tenant = tenant();
        $instance = (string) ($tenant?->wa_instance ?? '');

        if ($instance !== '') {
            try {
                $this->api->logout($instance);
            } catch (Throwable) {
                // الخروج على الخادم قد يفشل، والمقصود فكّ الارتباط عندنا
            }
        }

        $tenant?->forceFill(['wa_number' => null])->save();

        return back()->with('status', __('فُصل الرقم. يمكنك ربط رقمٍ آخر بمسح رمزٍ جديد.'));
    }

    /** رسالة تجربة إلى رقمٍ يكتبه المشترك — أوثق من «تمّ الحفظ» */
    public function test(Request $request): RedirectResponse
    {
        $this->authorise($request);

        $input = $request->validate(['number' => ['required', 'string', 'max:24']]);

        $tenant = tenant();
        $instance = (string) ($tenant?->wa_instance ?? '');

        if ($instance === '') {
            return back()->withErrors(['number' => __('اربط رقمك أولاً.')]);
        }

        try {
            $this->api->sendText(
                $instance,
                (string) ($tenant->wa_token ?? ''),
                $this->normalise((string) $input['number']),
                __('رسالة تجربة من :site — الربط يعمل.', ['site' => site_name()]),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['number' => $e->getMessage()]);
        }

        return back()->with('status', __('أُرسلت رسالة التجربة.'));
    }

    /** رقم مصري محلي يُحوَّل إلى صيغة دولية بلا + */
    private function normalise(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = '20'.substr($digits, 1);
        }

        return $digits;
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::SETTINGS_MANAGE), 403);
    }
}
