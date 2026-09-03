<?php

declare(strict_types=1);

namespace App\Core\Onboarding;

use App\Core\Tenancy\Actions\ApplyPlatformMode;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Theming\ThemeManager;
use App\Modules\Content\Actions\InstallSystemPages;
use App\Modules\Gamification\Actions\AwardBadges;
use Illuminate\Support\Facades\DB;

/**
 * ADR-010 — معالج التهيئة.
 *
 * اختيار المشترك هنا يُترجم فوراً إلى حالة فعلية: موديولات مفعّلة،
 * إعدادات افتراضية، ثيم، وقوائم لوحة تعرض ما يخصّه فقط.
 * قابل للإكمال لاحقاً: يتذكّر آخر خطوة وصل إليها.
 */
final class OnboardingWizard
{
    public const STEPS = ['mode', 'delivery', 'identity', 'locale', 'done'];

    public function __construct(
        private readonly ApplyPlatformMode $applyMode,
        private readonly ThemeManager $themes,
    ) {}

    public function currentStep(): string
    {
        $saved = (string) setting('onboarding.step', self::STEPS[0]);

        return in_array($saved, self::STEPS, true) ? $saved : self::STEPS[0];
    }

    public function isComplete(): bool
    {
        return setting('onboarding.completed_at') !== null;
    }

    public function stepIndex(?string $step = null): int
    {
        return (int) array_search($step ?? $this->currentStep(), self::STEPS, true);
    }

    /** @return list<array{key:string,label:string}> */
    public function stepLabels(): array
    {
        return [
            ['key' => 'mode', 'label' => __('نمط المنصة')],
            ['key' => 'delivery', 'label' => __('طريقة التقديم')],
            ['key' => 'identity', 'label' => __('الهوية')],
            ['key' => 'locale', 'label' => __('اللغة والعملة')],
        ];
    }

    /** الخطوة المسموح عرضها: لا يقفز المشترك إلى خطوة لم يبلغها. */
    public function canView(string $step): bool
    {
        return in_array($step, self::STEPS, true)
            && $this->stepIndex($step) <= $this->stepIndex();
    }

    /** @return array<string, array{name:array,summary:array,icon:string}> */
    public function modes(): array
    {
        return collect(config('platform-modes.modes'))
            ->map(fn (array $m): array => [
                'name' => $m['name'],
                'summary' => $m['summary'],
                'icon' => $m['icon'],
            ])
            ->all();
    }

    /** @return array<string, array{name:array}> */
    public function deliveryModes(): array
    {
        return config('platform-modes.delivery');
    }

    /** @return array<string, array<string, mixed>> */
    public function themesFor(string $mode): array
    {
        return $this->themes->forMode($mode);
    }

    // -----------------------------------------------------------------
    // حفظ الخطوات
    // -----------------------------------------------------------------

    public function saveMode(Tenant $tenant, string $mode, bool $centerEnabled): void
    {
        $tenant->forceFill(['platform_mode' => $mode, 'center_enabled' => $centerEnabled])->save();

        $this->advance('delivery');
    }

    public function saveDelivery(Tenant $tenant, string $delivery): void
    {
        $tenant->forceFill(['delivery_mode' => $delivery])->save();

        // الآن فقط نطبّق النمط: النمط وطريقة التقديم معاً يحدّدان الموديولات
        $this->applyMode->handle($tenant->refresh());

        $this->advance('identity');
    }

    /** @param  array{name:string, theme:string, primary_color?:string, tagline?:string}  $input */
    public function saveIdentity(Tenant $tenant, array $input): void
    {
        $tenant->forceFill([
            'name' => $input['name'],
            'theme' => $this->themes->exists($input['theme']) ? $input['theme'] : $tenant->theme,
        ])->save();

        setting()->setMany(array_filter([
            'appearance.primary_color' => $input['primary_color'] ?? null,
            'site.tagline' => $input['tagline'] ?? null,
        ], fn ($v) => $v !== null));

        $this->advance('locale');
    }

    /** @param  array{locale:string, country:string, currency:string, numerals:string}  $input */
    public function saveLocale(Tenant $tenant, array $input): void
    {
        $tenant->forceFill([
            'locale' => $input['locale'],
            'country' => $input['country'],
            'currency' => $input['currency'],
            'timezone' => DB::connection(config('tenancy.database.central_connection'))
                ->table('countries')->where('code', $input['country'])->value('timezone_default') ?? $tenant->timezone,
        ])->save();

        setting()->set('appearance.numerals', $input['numerals']);

        $this->advance('done');
    }

    public function complete(): void
    {
        setting()->setMany([
            'onboarding.step' => 'done',
            'onboarding.completed_at' => now()->toIso8601String(),
        ]);

        /*
         | ما لا معنى لموقع بدونه يُنشأ هنا لا يُترك للمشترك:
         | صفحات السياسات شرط بوابات الدفع، والشارات بلا شارات
         | معرّفة تجعل شاشة التقدّم فارغة يوم الإطلاق.
         */
        app(InstallSystemPages::class)->handle();
        app(AwardBadges::class)->install();
    }

    private function advance(string $step): void
    {
        // لا نتراجع: من عاد لتعديل خطوة سابقة لا يفقد تقدّمه
        if ($this->stepIndex($step) > $this->stepIndex()) {
            setting()->set('onboarding.step', $step);
        }
    }
}
