<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Access\Ability;
use App\Core\Notifications\EventCatalogue;
use App\Modules\Webhooks\Jobs\DeliverWebhook;
use App\Modules\Webhooks\Models\WebhookDelivery;
use App\Modules\Webhooks\Models\WebhookEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * وجهات الـWebhooks — إنشاؤها ومتابعة تسليمها.
 *
 * ## والشاشة تعرض السجلّ لا الوجهة وحدها
 *
 * أوّل سؤالٍ في كل تكاملٍ لا يعمل: «هل أرسلتم؟». وشاشةٌ تعرض
 * الوجهة بلا سجلّ تسليمها تترك السؤال بلا جواب، فيُفتَح لنا تذكرة.
 */
final class WebhookEndpointController
{
    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('tenant.webhooks', [
            'endpoints' => WebhookEndpoint::withCount('deliveries')->latest('id')->get(),

            'events' => app(EventCatalogue::class)->grouped(),

            /*
             | آخر أربعين تسليماً — لا الجدول كلّه.
             |
             | منصّةٌ نشطة تُسلّم آلافاً في اليوم، وما يُبحث عنه
             | دائماً هو الأخير: «جرّبتُ الآن، هل وصل؟»
             */
            'deliveries' => WebhookDelivery::with('endpoint:id,name')
                ->latest('id')->limit(40)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorise($request);

        $input = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:https', 'max:2000'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'max:64'],
        ], [], [
            'name' => __('الاسم'),
            'url' => __('الرابط'),
            'events' => __('الأحداث'),
        ]);

        /*
         | HTTPS وحدها.
         |
         | الحمولة تحمل أسماء الطلبة وبريدهم ومبالغ دفعهم؛ وإرسالها
         | على HTTP يضعها في يد كل من يمرّ بها في الطريق.
         */

        $endpoint = WebhookEndpoint::create([
            'name' => $input['name'],
            'url' => $input['url'],
            'events' => $this->knownEvents($input['events']),
            'is_active' => true,
            'source' => 'manual',
        ]);

        return back()
            ->with('status', __('أُضيفت الوجهة. انسخ سرّها الآن — لن يُعرض مرة أخرى.'))
            ->with('webhook_secret', $endpoint->secret);
    }

    /** تجربةٌ فورية — كي لا يُكتشف الخطأ عند أوّل طالبٍ حقيقي */
    public function test(Request $request, int $id): RedirectResponse
    {
        $this->authorise($request);

        $endpoint = WebhookEndpoint::findOrFail($id);

        DeliverWebhook::dispatch($endpoint->getKey(), 'platform.test', [
            'message' => __('رسالة تجربة من :site', ['site' => site_name()]),
            'at' => now()->toIso8601String(),
        ]);

        return back()->with('status', __('أُرسلت رسالة التجربة — راجع السجلّ بعد لحظات.'));
    }

    /** إعادة تشغيل وجهةٍ أوقفها تراكم الإخفاقات */
    public function resume(Request $request, int $id): RedirectResponse
    {
        $this->authorise($request);

        WebhookEndpoint::whereKey($id)->update([
            'disabled_at' => null,
            'consecutive_failures' => 0,
            'is_active' => true,
        ]);

        return back()->with('status', __('أُعيد تشغيل الوجهة.'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->authorise($request);

        WebhookEndpoint::whereKey($id)->delete();

        return back()->with('status', __('حُذفت الوجهة.'));
    }

    /**
     * لا يُحفظ إلا ما يعرفه الكتالوج.
     *
     * حدثٌ مكتوبٌ بخطأ مطبعي يُحفظ فلا يُطلق أبداً، ويبحث المشترك
     * عن العلّة في خادمه شهراً.
     *
     * @param  list<string>  $events
     * @return list<string>
     */
    private function knownEvents(array $events): array
    {
        if (in_array('*', $events, true)) {
            return ['*'];
        }

        $catalogue = app(EventCatalogue::class);

        return array_values(array_filter(
            $events,
            fn (string $event): bool => $catalogue->has($event),
        ));
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::SETTINGS_MANAGE), 403);
    }
}
