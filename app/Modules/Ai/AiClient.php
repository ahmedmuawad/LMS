<?php

declare(strict_types=1);

namespace App\Modules\Ai;

use App\Core\Entitlements\Quota;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * عميلٌ واحد لمزوّدَي الذكاء الاصطناعي.
 *
 * ## مفتاح المنصة أولاً ثم مفتاح المشترك
 *
 * مدرّسٌ لا يملك حساب OpenAI ولا يريد أن يملكه، فالمنصة تحمل
 * المفتاح وتحاسب بحدٍّ في الباقة. ومن وضع مفتاحه استُعمل بدلاً عنه
 * ولم يُحسب عليه حدّ — فهو يدفع لمزوّده مباشرةً.
 *
 * ## ولا يُخزَّن ما يُرسَل
 *
 * المحتوى ملكُ المشترك: أسئلة امتحاناته ومناهجه. فلا يُحفظ نصُّ
 * الطلب ولا الجواب — يُحفظ عدد الرموز للمحاسبة وحده.
 */
final class AiClient
{
    public function __construct(private readonly Quota $quota) {}

    /**
     * يسأل النموذج ويعيد النصّ.
     *
     * @throws RuntimeException
     */
    public function ask(string $system, string $prompt, bool $json = false): string
    {
        $provider = $this->provider();
        $ownKey = $this->tenantKey();

        // الحدّ يُفحص قبل الطلب — لا بعد أن نُنفق
        if ($ownKey === null) {
            $this->quota->enforce('ai_requests');
        }

        $key = $ownKey ?? ($provider['key'] ?? null);

        if (blank($key)) {
            throw new RuntimeException(__('لم تُضبط مفاتيح الذكاء الاصطناعي بعد.'));
        }

        $prompt = mb_substr($prompt, 0, (int) config('ai.max_input_chars'));

        $response = $this->name() === 'anthropic'
            ? $this->callAnthropic($provider, (string) $key, $system, $prompt, $json)
            : $this->callOpenAi($provider, (string) $key, $system, $prompt, $json);

        if ($ownKey === null) {
            $this->quota->record('ai_requests');
        }

        return $response;
    }

    /**
     * يسأل ويتوقّع JSON — ويُصلح ما حوله من كلام.
     *
     * النماذج تُحيط الجواب بشرحٍ أحياناً رغم الطلب، ورفضُ الجواب
     * كلّه لأجل سطرٍ زائد يُفشل عملاً صحيحاً.
     *
     * @return array<mixed>
     *
     * @throws RuntimeException
     */
    public function askJson(string $system, string $prompt): array
    {
        $raw = trim($this->ask($system, $prompt, json: true));

        $start = strcspn($raw, '{[');
        $raw = mb_substr($raw, $start);
        $raw = rtrim($raw, "` \n\r\t");

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new RuntimeException(__('لم نفهم جواب النموذج. أعد المحاولة.'));
        }

        return $decoded;
    }

    /** @param array<string, mixed> $provider */
    private function callOpenAi(array $provider, string $key, string $system, string $prompt, bool $json): string
    {
        $payload = [
            'model' => $this->model($provider),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => (int) config('ai.max_output_tokens'),
        ];

        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken($key)
            ->timeout((int) config('ai.timeout_seconds'))
            ->post((string) $provider['endpoint'], $payload);

        $this->assertOk($response->status(), (string) $response->body());

        return (string) ($response->json('choices.0.message.content') ?? '');
    }

    /** @param array<string, mixed> $provider */
    private function callAnthropic(array $provider, string $key, string $system, string $prompt, bool $json): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout((int) config('ai.timeout_seconds'))
            ->post((string) $provider['endpoint'], [
                'model' => $this->model($provider),
                'system' => $system.($json ? "\n\nأجب بـJSON صالح وحده، بلا أي نصّ قبله أو بعده." : ''),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => (int) config('ai.max_output_tokens'),
            ]);

        $this->assertOk($response->status(), (string) $response->body());

        return (string) ($response->json('content.0.text') ?? '');
    }

    /**
     * الأخطاء تُترجَم إلى ما يفهمه المدرّس.
     *
     * «401 Unauthorized» لا تعني له شيئاً، و«مفتاحك غير صالح» تعني
     * كل شيء. ولا يُعاد نصُّ المزوّد كما هو: قد يحمل تفصيلاً عن
     * حسابنا نحن.
     */
    private function assertOk(int $status, string $body): void
    {
        if ($status >= 200 && $status < 300) {
            return;
        }

        throw new RuntimeException(match (true) {
            $status === 401 || $status === 403 => __('مفتاح الذكاء الاصطناعي غير صالح.'),
            $status === 429 => __('الطلبات كثيرة على المزوّد الآن. أعد المحاولة بعد قليل.'),
            $status >= 500 => __('المزوّد لا يستجيب الآن. أعد المحاولة بعد قليل.'),
            default => __('تعذّر إتمام الطلب.'),
        });
    }

    private function name(): string
    {
        return (string) (setting('integrations.ai_provider') ?: config('ai.default', 'openai'));
    }

    /** @return array<string, mixed> */
    private function provider(): array
    {
        $providers = (array) config('ai.providers', []);

        return $providers[$this->name()] ?? $providers['openai'] ?? [];
    }

    /** @param array<string, mixed> $provider */
    private function model(array $provider): string
    {
        return (string) (setting('integrations.ai_model') ?: ($provider['model'] ?? 'gpt-4o-mini'));
    }

    /** مفتاح المشترك إن وضعه — يُستعمل بدلاً عن مفتاحنا ولا يُحسب عليه حدّ */
    private function tenantKey(): ?string
    {
        $key = setting('integrations.ai_key');

        return filled($key) ? (string) $key : null;
    }
}
