<?php

declare(strict_types=1);

namespace App\Core\Invoicing;

use App\Core\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * الفاتورة الإلكترونية المصرية — مصلحة الضرائب (ETA).
 *
 * ## ما الذي يعمل هنا وما الذي يقف
 *
 * يعمل: بناءُ المستند بصيغة المصلحة، والدخولُ بمفاتيح المشترك،
 * والإرسالُ إلى بوّابتها، وقراءةُ ردّها وحفظُ رقم القبول (UUID).
 *
 * ويقف عند خطوةٍ واحدة: **التوقيع الإلكتروني**. المصلحة تشترط
 * توقيعاً بشهادةٍ على توكن USB أو HSM باسم المموّل نفسه — جهازٌ
 * في يده هو، لا في خادمنا. ولا يستطيع أحدٌ الالتفاف عليه.
 *
 * ## ولماذا يُبنى ما لا يكتمل
 *
 * لأن ما يُبنى هنا هو تسعةُ أعشار العمل: من عنده التوكن يُشغّل
 * أداة المصلحة على جهازه فتوقّع المستند الذي نُصدره، ثم يُرسَل من
 * هنا. ومنصّةٌ لا تبني المستند أصلاً تترك محاسبَه يكتبه بيده في
 * بوّابة المصلحة، فاتورةً فاتورة.
 *
 * ## والبيئة تُختار لا تُفترض
 *
 * المصلحة لها بوّابةُ تجريبٍ وأخرى حقيقية، والخلط بينهما يعني
 * فواتير تجريبيةً في السجلّ الحقيقي — ولا تُحذف.
 */
final class EgyptEta
{
    private const HOSTS = [
        'preprod' => [
            'id' => 'https://id.preprod.eta.gov.eg',
            'api' => 'https://api.preprod.invoicing.eta.gov.eg/api/v1',
        ],
        'production' => [
            'id' => 'https://id.eta.gov.eg',
            'api' => 'https://api.invoicing.eta.gov.eg/api/v1',
        ],
    ];

    public function enabled(): bool
    {
        return (bool) setting('currency.eta_enabled', false)
            && filled(setting('currency.eta_client_id'))
            && filled(setting('currency.eta_client_secret'));
    }

    /**
     * المستند بصيغة المصلحة — جاهزاً للتوقيع ثم الإرسال.
     *
     * @param  array<int, array{name:string, quantity:int|float, unitPrice:Money, total:Money}>  $lines
     * @return array<string, mixed>
     */
    public function document(
        string $number,
        Carbon $issuedAt,
        array $lines,
        Money $total,
        Money $vat,
        array $buyer = [],
    ): array {
        return [
            'issuer' => [
                'type' => 'B',   // منشأة
                'id' => (string) setting('currency.tax_number'),
                'name' => (string) (setting()->translated('currency.company_name') ?: site_name()),
                'address' => $this->address(),
            ],

            /*
             | والمشتري «شخص طبيعي» ما لم يُعطِ رقماً ضريبياً.
             |
             | طالبٌ يشتري كورساً ليس منشأة، وإرسالُه كمنشأةٍ بلا
             | رقمٍ ضريبي يُرفَض المستند كلّه — والرفض يأتي بعد يوم.
             */
            'receiver' => [
                'type' => filled($buyer['tax_number'] ?? null) ? 'B' : 'P',
                'id' => (string) ($buyer['tax_number'] ?? $buyer['national_id'] ?? ''),
                'name' => (string) ($buyer['name'] ?? __('مستهلك نهائي')),
            ],

            'documentType' => 'I',   // فاتورة
            'documentTypeVersion' => '1.0',
            'dateTimeIssued' => $issuedAt->utc()->format('Y-m-d\TH:i:s\Z'),
            'taxpayerActivityCode' => (string) setting('currency.eta_activity_code', '8542'),
            'internalID' => $number,

            'invoiceLines' => array_map(fn (array $line): array => [
                'description' => (string) $line['name'],
                'itemType' => (string) setting('currency.eta_item_code_type', 'EGS'),
                'itemCode' => (string) ($line['code'] ?? setting('currency.eta_item_code', '')),
                'unitType' => 'EA',
                'quantity' => (float) $line['quantity'],
                'unitValue' => [
                    'currencySold' => $line['unitPrice']->currency,
                    'amountEGP' => (float) $line['unitPrice']->toDecimal(),
                ],
                'salesTotal' => (float) $line['total']->toDecimal(),
                'total' => (float) $line['total']->toDecimal(),
                'netTotal' => (float) $line['total']->toDecimal(),
                'itemsDiscount' => 0,
            ], $lines),

            'totalSalesAmount' => (float) $total->minus($vat)->toDecimal(),
            'totalDiscountAmount' => 0,
            'netAmount' => (float) $total->minus($vat)->toDecimal(),
            'totalAmount' => (float) $total->toDecimal(),
            'taxTotals' => [
                ['taxType' => 'T1', 'amount' => (float) $vat->toDecimal()],
            ],
            'extraDiscountAmount' => 0,
            'totalItemsDiscountAmount' => 0,

            /*
             | التوقيع يُترك فارغاً هنا.
             |
             | يملؤه المموّل بشهادته على توكن USB — ولا يقبل المستندَ
             | بلا توقيعٍ صالح. وتركُ المكان معلوماً أوضح من إخفائه.
             */
            'signatures' => [],
        ];
    }

    /**
     * رمز الدخول — يُطلب مرّة ويُحفظ حتى يقارب انتهاءه.
     *
     * @throws RuntimeException
     */
    public function token(): string
    {
        $key = 'eta:token:'.tenant('id');

        $cached = Cache::get($key);

        if (is_string($cached)) {
            return $cached;
        }

        $response = Http::asForm()->timeout(20)->post($this->host('id').'/connect/token', [
            'grant_type' => 'client_credentials',
            'client_id' => (string) setting('currency.eta_client_id'),
            'client_secret' => (string) setting('currency.eta_client_secret'),
        ]);

        if ($response->failed()) {
            throw new RuntimeException(__('تعذّر الدخول إلى بوّابة المصلحة — راجع مفاتيحك.'));
        }

        $token = (string) $response->json('access_token');
        $expires = (int) $response->json('expires_in', 3600);

        // بدقيقةٍ قبل انتهائه: رمزٌ ينتهي أثناء الإرسال يُفقد الفاتورة
        Cache::put($key, $token, now()->addSeconds(max(60, $expires - 60)));

        return $token;
    }

    /**
     * إرسال مستندٍ موقَّع.
     *
     * @param  array<string, mixed>  $signed  المستند بعد أن وقّعه المموّل
     * @return array{uuid:?string, accepted:bool, errors:array<int, mixed>}
     *
     * @throws RuntimeException
     */
    public function submit(array $signed): array
    {
        if (($signed['signatures'] ?? []) === []) {
            throw new RuntimeException(
                __('المستند بلا توقيع — وقّعه بشهادتك على التوكن قبل الإرسال.'),
            );
        }

        $response = Http::withToken($this->token())->timeout(60)
            ->post($this->host('api').'/documentsubmissions', ['documents' => [$signed]]);

        if ($response->failed()) {
            throw new RuntimeException(__('رفضت المصلحة الإرسال: :message', [
                'message' => (string) $response->json('error.message', $response->status()),
            ]));
        }

        $accepted = (array) $response->json('acceptedDocuments', []);

        return [
            'uuid' => $accepted[0]['uuid'] ?? null,
            'accepted' => $accepted !== [],
            'errors' => (array) $response->json('rejectedDocuments', []),
        ];
    }

    private function host(string $which): string
    {
        $environment = setting('currency.eta_environment', 'preprod') === 'production'
            ? 'production'
            : 'preprod';

        return self::HOSTS[$environment][$which];
    }

    /** @return array<string, string> */
    private function address(): array
    {
        return [
            'country' => 'EG',
            'governate' => (string) setting('currency.eta_governate', ''),
            'regionCity' => (string) setting('currency.eta_city', ''),
            'street' => (string) (setting()->translated('currency.company_address') ?: ''),
            'buildingNumber' => (string) setting('currency.eta_building', ''),
            'branchID' => (string) setting('currency.eta_branch', '0'),
        ];
    }
}
