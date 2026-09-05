<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Modules\Lms\Actions\RecordXapiStatement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * نقطة استقبال عبارات xAPI (Experience API) من خارج المنصة.
 *
 * المعيار يصف التعلّم جملةً: «فلانٌ أتمّ الدرس الفلاني». ويصلح لما
 * لا يصلح له SCORM — نشاطٌ خارج المنصة، أو تطبيقٌ يرسل ما فعله
 * الطالب فيه.
 *
 * ## ما نُنفّذه منه وما لا نُنفّذه
 *
 * المعيار كبير: حالاتٌ ووثائقُ وأنشطةٌ ووكلاء. وما يحتاجه المدرّس
 * جملةٌ واحدة: «من فعل ماذا بأيّ نتيجة». فنستقبل `POST /statements`
 * ونقرأ منها الفعل والهدف والنتيجة، ونحفظ العبارة كاملةً كما وصلت
 * لمن يريد تصديرها إلى LRS حقيقي لاحقاً.
 *
 * ونقول ذلك صراحةً بدل ادّعاء توافقٍ كامل: مشترٍ يظنّ أننا LRS
 * كامل يكتشف النقص بعد أن يبني عليه.
 */
final class XapiController
{
    public function __construct(private readonly RecordXapiStatement $record) {}

    public function store(Request $request): JsonResponse
    {
        /*
         | العبارة تُقبل مفردةً أو مصفوفة.
         |
         | فأكثر المُرسِلين يرسلون واحدة، وبعضهم يرسل دفعةً عند عودة
         | الاتصال — ورفضُ الدفعة يُضيّع ما وقع بلا إنترنت.
         */
        $payload = $request->json()->all();
        $rows = is_array($payload) && array_is_list($payload) ? $payload : [$payload];

        if (count($rows) > 100) {
            return response()->json(['message' => __('أكثر من مئة عبارة في الطلب الواحد.')], 413);
        }

        $ids = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = $this->record->handle($row);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        // المعيار يطلب ردّ المعرّفات: المُرسِل يطابقها بما أرسل
        return response()->json($ids, 200);
    }
}
