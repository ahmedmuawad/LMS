<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Audit\Audit;
use App\Core\Billing\PlatformSettings;
use App\Core\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Modules\Whatsapp\EvolutionApi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

/**
 * بوّابة واتساب — إعداد المنصّة، ونُسَخ المشتركين.
 *
 * صاحب المنصّة يضبط الخادم ومفتاحه مرّةً، ثم يربط كل مشترك رقمه
 * بنفسه من لوحته. والمفتاح العام لا يخرج من هنا أبداً: من وصله
 * حذف نُسَخ غيره.
 */
final class WhatsAppGatewayController extends Controller
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly EvolutionApi $api,
    ) {}

    public function edit(): View
    {
        $tenants = Tenant::orderBy('name')->get()->map(function (Tenant $tenant): array {
            $instance = (string) ($tenant->wa_instance ?? '');

            return [
                'tenant' => $tenant,
                'instance' => $instance,
                'state' => $instance !== '' && $this->api->configured()
                    ? $this->safeState($instance)
                    : null,
            ];
        });

        return $this->view($tenants);
    }

    public function update(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'server_url' => ['nullable', 'url', 'max:255'],
            'global_key' => ['nullable', 'string', 'max:255'],
        ]);

        $this->settings->set('whatsapp_server_url', $input['server_url'] ?? null);

        /*
         | المفتاح لا يُمسح بحقلٍ فارغ.
         |
         | الشاشة لا تعرضه (سرٌّ لا يُعاد عرضه)، ومن حفظ الصفحة
         | لتعديل العنوان وحده يُرسل خانةً فارغة — فيفقد المفتاح بلا
         | أن يقصد.
         */
        if (filled($input['global_key'] ?? null)) {
            $this->settings->set('whatsapp_global_key', $input['global_key'], encrypted: true);
        }

        Audit::log('platform.whatsapp.updated');

        return back()->with('status', __('حُفظت بوّابة واتساب.'));
    }

    /** حذف نسخة مشترك من الخادم — حين يتعطّل ربطه ويحتاج بداية نظيفة */
    public function reset(Request $request, string $tenantId): RedirectResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $instance = (string) ($tenant->wa_instance ?? '');

        if ($instance !== '') {
            try {
                $this->api->delete($instance);
            } catch (Throwable) {
                // الخادم قد لا يعرفها أصلاً — والمقصود أن تختفي، وقد اختفت
            }
        }

        $tenant->forceFill(['wa_instance' => null, 'wa_token' => null, 'wa_number' => null])->save();

        Audit::log('platform.whatsapp.reset', ['tenant' => $tenantId]);

        return back()->with('status', __('حُذفت نسخة المشترك — يربط رقمه من جديد.'));
    }

    /** @param Collection<int, array<string, mixed>> $tenants */
    private function view($tenants): View
    {
        return view('super-admin.whatsapp', [
            'server' => $this->api->server(),
            'hasKey' => filled($this->settings->get('whatsapp_global_key')),
            'tenants' => $tenants,
        ]);
    }

    private function safeState(string $instance): string
    {
        try {
            return $this->api->state($instance);
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
