<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Notifications\EventCatalogue;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Webhooks\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * اشتراك Zapier وأمثاله — «REST Hooks».
 *
 * ## لماذا لا يكفي أن يكتب المشترك الرابط بيده
 *
 * Zapier لا يعطي رابطاً ثابتاً: يولّده عند تشغيل «الزاب» ويلغيه
 * عند إيقافه. فيجب أن يُسجّل نفسه ويحذف نفسه — وإلّا بقي المشترك
 * ينسخ روابط ويلصقها كلّما عدّل زاباً.
 *
 * ## والاشتراك مربوطٌ بمفتاحه
 *
 * حسابٌ فُصل عن Zapier يجب أن تموت اشتراكاته معه؛ وبلا ربطٍ
 * بالمفتاح تبقى وجهاتٌ يتيمة ترسل إلى حسابٍ لم يعد أحدٌ يقرؤه.
 */
final class WebhookSubscriptionController
{
    /** POST /api/v1/webhooks — يسجّل وجهة */
    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'url' => ['required', 'url:https', 'max:2000'],
            'event' => ['required', 'string', 'max:64'],
        ]);

        $event = (string) $input['event'];

        if ($event !== '*' && ! app(EventCatalogue::class)->has($event)) {
            return response()->json([
                'message' => __('حدث غير معروف.'),
                'available' => array_keys(app(EventCatalogue::class)->available()),
            ], 422);
        }

        $token = $request->attributes->get('api_token');

        $endpoint = WebhookEndpoint::create([
            'name' => 'Zapier — '.$event,
            'url' => (string) $input['url'],
            'events' => [$event],
            'is_active' => true,
            'source' => 'api',
            'token_id' => $token instanceof ApiToken ? $token->getKey() : null,
        ]);

        /*
         | السرّ يُعاد في جواب الإنشاء.
         |
         | المشترِك آلةٌ لا إنسان: لا يفتح شاشةً لينسخه، وبلا سرٍّ
         | لا يستطيع التحقّق من أن ما يصله منّا.
         */
        return response()->json([
            'id' => $endpoint->getKey(),
            'url' => $endpoint->url,
            'event' => $event,
            'secret' => $endpoint->secret,
        ], 201);
    }

    /** DELETE /api/v1/webhooks/{id} — يُلغي اشتراكه هو لا اشتراك غيره */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $token = $request->attributes->get('api_token');

        $endpoint = WebhookEndpoint::whereKey($id)->where('source', 'api')->first();

        /*
         | مفتاحٌ لا يحذف إلا ما سجّله.
         |
         | وبلا هذا الشرط يستطيع تكاملٌ أن يُسكِت تكاملاً آخر في
         | المنصّة نفسها — بلا أن يعلم صاحبها.
         */
        if ($endpoint === null
            || ($token instanceof ApiToken && $endpoint->token_id !== $token->getKey())) {
            return response()->json(['message' => __('لا يوجد اشتراك بهذا الرقم.')], 404);
        }

        $endpoint->delete();

        return response()->json(['deleted' => true]);
    }

    /** GET /api/v1/webhooks/events — ما يمكن الاشتراك فيه */
    public function events(): JsonResponse
    {
        return response()->json([
            'events' => collect(app(EventCatalogue::class)->available())
                ->map(fn ($event, string $key): array => [
                    'key' => $key,
                    'label' => __($event->label),
                    'group' => $event->group,
                ])->values(),
        ]);
    }
}
