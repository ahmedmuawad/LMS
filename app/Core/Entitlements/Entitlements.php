<?php

declare(strict_types=1);

namespace App\Core\Entitlements;

use App\Core\Entitlements\Models\Feature;
use App\Core\Entitlements\Models\PlanFeature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * ADR-011 — طبقة الصلاحيات.
 *
 * كل ميزة في المنصة مبنية بالكامل؛ هذه الطبقة وحدها تقرّر ما هو متاح
 * لهذا المشترك. ترتيب الأولوية: تجاوز المشترك ← باقة المشترك ← الافتراضي.
 *
 * القيمة "unlimited" تعني بلا حد. غياب المفتاح يعني ممنوع (للمنطقية) أو صفر (للحدود).
 */
final class Entitlements
{
    public const UNLIMITED = 'unlimited';

    private const VERSION_KEY = 'entitlements_version';

    public function __construct(
        private readonly string $tenantId,
        private readonly ?string $planKey,
    ) {}

    /** ميزة منطقية: هل هي متاحة لهذا المشترك؟ */
    public function allows(string $feature): bool
    {
        $value = $this->value($feature);

        return $value !== null && $value !== '0' && $value !== 'false';
    }

    /** الحد الأقصى. null تعني بلا حد. 0 تعني ممنوع. */
    public function limit(string $feature): ?int
    {
        $value = $this->value($feature);

        if ($value === null) {
            return 0;
        }

        return $value === self::UNLIMITED ? null : (int) $value;
    }

    public function isUnlimited(string $feature): bool
    {
        return $this->value($feature) === self::UNLIMITED;
    }

    /** الاستهلاك الحالي — من عدّاد محدَّث بالأحداث، لا COUNT() لحظي. */
    public function usage(string $feature): int
    {
        return (int) DB::connection($this->centralConnection())
            ->table('usage_records')
            ->where('tenant_id', $this->tenantId)
            ->where('feature_key', $feature)
            ->where(fn ($q) => $q->whereNull('period')->orWhere('period', now()->format('Y-m')))
            ->value('used') ?? 0;
    }

    public function remaining(string $feature): ?int
    {
        $limit = $this->limit($feature);

        return $limit === null ? null : max(0, $limit - $this->usage($feature));
    }

    /**
     * هل بلغ الحد؟ الحدّ يمنع «الإضافة الجديدة» فقط —
     * ما هو قائم يبقى يعمل، ولا يُحرم طالب دفع من محتواه أبداً.
     */
    public function hasReachedLimit(string $feature): bool
    {
        $limit = $this->limit($feature);

        return $limit !== null && $this->usage($feature) >= $limit;
    }

    /** نسبة الاستهلاك 0..100 — تغذّي تنبيهات 80% و95%. */
    public function usagePercent(string $feature): ?float
    {
        $limit = $this->limit($feature);

        if ($limit === null || $limit === 0) {
            return null;
        }

        return round(min(100, $this->usage($feature) / $limit * 100), 1);
    }

    public function recordUsage(string $feature, int $delta = 1): void
    {
        $period = Feature::on($this->centralConnection())->find($feature)?->isQuota()
            ? now()->format('Y-m')
            : null;

        $conn = DB::connection($this->centralConnection());
        $match = [
            'tenant_id' => $this->tenantId,
            'feature_key' => $feature,
            'period' => $period,
        ];

        // GREATEST/MAX تختلف بين محرّكات القواعد، فنحسبها في PHP داخل معاملة
        $conn->transaction(function () use ($conn, $match, $delta): void {
            $current = (int) $conn->table('usage_records')
                ->where($match)
                ->lockForUpdate()
                ->value('used');

            $conn->table('usage_records')->updateOrInsert($match, [
                'used' => max(0, $current + $delta),
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        });
    }

    /** @return array<string, string> كل المزايا الفعّالة — للعرض في اللوحة */
    public function all(): array
    {
        return $this->resolved();
    }

    public function flush(): void
    {
        Cache::forget($this->cacheKey());
    }

    private function value(string $feature): ?string
    {
        return $this->resolved()[$feature] ?? null;
    }

    /** @return array<string, string> */
    private function resolved(): array
    {
        return Cache::remember($this->cacheKey(), now()->addHour(), function (): array {
            $conn = $this->centralConnection();

            $fromPlan = $this->planKey
                ? PlanFeature::on($conn)->where('plan_key', $this->planKey)->pluck('value', 'feature_key')->all()
                : [];

            $overrides = DB::connection($conn)->table('tenant_features')
                ->where('tenant_id', $this->tenantId)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->pluck('value', 'feature_key')
                ->all();

            return [...$fromPlan, ...$overrides];
        });
    }

    /**
     * المفتاح يحمل نسخة الباقات.
     *
     * كان يحمل معرّف المشترك وباقته فقط، ويُخزَّن ساعةً كاملة. فحين
     * تتغيّر حدود الباقة — بترقية أو بتصحيح أو بإعادة بذر — يبقى
     * المشترك ساعةً كاملة على الحدود القديمة. والأسوأ من التأخّر أن
     * الحدّ المضاف حديثاً يُقرأ **غائباً**، وغيابُ الحدّ يعني صفراً
     * لا «بلا حدّ» — فيُمنع من كل شيء.
     *
     * ورقمُ النسخة يُرفع مع كل تغيير في `plan_features`، فتسقط كل
     * المفاتيح القديمة دفعةً واحدة بلا مسحٍ للكاش كلّه.
     */
    private function cacheKey(): string
    {
        return 'entitlements:v'.self::version().":{$this->tenantId}:".($this->planKey ?? 'none');
    }

    /**
     * نسخة الباقات الحالية — رقمٌ في القاعدة المركزية.
     *
     * في القاعدة لا في الكاش: الكاش هو ما نُبطله، فوضعُ مفتاح
     * الإبطال فيه يجعله يسقط مع ما يُبطله.
     */
    public static function version(): int
    {
        try {
            return (int) DB::connection(config('tenancy.database.central_connection'))
                ->table('platform_settings')
                ->where('key', self::VERSION_KEY)
                ->value('value') ?: 1;
        } catch (Throwable) {
            // قبل الهجرات أو خارج سياق قاعدة: نسخةٌ ثابتة خيرٌ من انفجار
            return 1;
        }
    }

    /**
     * يرفع النسخة فتسقط كل الاستحقاقات المخزَّنة.
     *
     * يُستدعى من كل ما يغيّر حدود الباقات: بذر الباقات، وتغيير باقة
     * مشترك، وتجاوزات المشترك.
     */
    public static function bumpVersion(): void
    {
        $conn = DB::connection(config('tenancy.database.central_connection'));

        try {
            $current = self::version();

            $conn->table('platform_settings')->updateOrInsert(
                ['key' => self::VERSION_KEY],
                ['value' => (string) ($current + 1), 'updated_at' => now(), 'created_at' => now()],
            );
        } catch (Throwable) {
            // لا نُفشل عملية العميل لأن الإبطال تعذّر
        }
    }

    private function centralConnection(): ?string
    {
        return config('tenancy.database.central_connection');
    }
}
