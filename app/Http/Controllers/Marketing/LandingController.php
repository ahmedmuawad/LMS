<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Core\Entitlements\Models\Plan;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صفحة موقعنا نحن — لا صفحة مشترك.
 *
 * الباقات والأنماط تُقرأ من مصدرها الحيّ (جدول plans وملف
 * platform-modes) لا من نصّ مكتوب في القالب: صفحة أسعار تكذب
 * على لوحة الإدارة أسوأ من صفحة بلا أسعار.
 */
final class LandingController extends Controller
{
    /** الحدود التي تُعرض كأرقام في بطاقة الباقة، بترتيب العرض. */
    private const HEADLINE_LIMITS = ['students', 'instructors', 'courses', 'storage_gb'];

    /**
     * المزايا التي يشتري الناس الباقة من أجلها، بترتيب الأهمية.
     * ما يزيد عنها يُعدّ ولا يُسرد — بطاقة بعشرين سطراً لا تُقرأ.
     */
    private const HEADLINE_FEATURES = [
        'center_management', 'parent_portal', 'attendance_devices', 'center_finance',
        'custom_domain', 'white_label', 'mobile_app', 'multi_instructor',
        'recharge_codes', 'installments', 'services_module', 'physical_products',
        'interactive_video', 'scorm', 'proctoring', 'adaptive_learning',
        'offline_download', 'drm', 'screenshot_block', 'video_watermark',
        'funnels', 'email_automation', 'whatsapp_api', 'affiliates',
        'gamification', 'community', 'page_builder', 'ai_assistant',
        'ai_course_builder', 'ai_exam_from_pdf', 'api_access', 'priority_support',
        'live_zoom', 'live_meet', 'live_bbb', 'live_jitsi',
        'multi_language', 'multi_currency', 'custom_css', 'blog',
        'h5p', 'xapi', 'inventory', 'data_residency',
    ];

    public function __invoke(): View
    {
        $plans = $this->plans();

        return view('marketing.landing', [
            'plans' => $plans,
            'currencies' => $this->currencies($plans),
            'featureNames' => $this->featureNames(),
            'headlineLimits' => self::HEADLINE_LIMITS,
            'headlineFeatures' => self::HEADLINE_FEATURES,
            'modes' => $this->modes(),
        ]);
    }

    /**
     * الباقات المعروضة للبيع، مرتّبة كما رتّبتها لوحة الإدارة.
     *
     * @return Collection<int, Plan>
     */
    private function plans(): Collection
    {
        /*
         | جدول مركزي: على بيئة لم تُهاجَر بعد نعرض الصفحة بلا أسعار
         | بدل أن نُسقطها — الصفحة التسويقية آخر ما يجوز أن ينهار.
         */
        if (! Schema::hasTable('plans')) {
            return collect();
        }

        return Plan::query()
            ->with('features')
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('position')
            ->get();
    }

    /**
     * العملات المعروضة = ما سُعِّرت به الباقات فعلاً.
     * (ADR-014: تسعير مثبّت لكل عملة، لا تحويل بسعر الصرف.)
     *
     * @param  Collection<int, Plan>  $plans
     * @return list<string>
     */
    private function currencies(Collection $plans): array
    {
        $keys = $plans
            ->flatMap(fn (Plan $plan): array => array_keys($plan->prices ?? []))
            ->unique()
            ->values()
            ->all();

        // ترتيب سوقي مقصود: مصر أولاً، ثم الخليج، ثم الدولي
        $preferred = ['EGP', 'SAR', 'AED', 'USD'];

        usort($keys, function (string $a, string $b) use ($preferred): int {
            $ai = array_search($a, $preferred, true);
            $bi = array_search($b, $preferred, true);

            return ($ai === false ? PHP_INT_MAX : $ai) <=> ($bi === false ? PHP_INT_MAX : $bi);
        });

        return $keys;
    }

    /**
     * أنماط المنصّة كما يعرّفها ملف الإعداد الذي يبني اللوحات فعلاً.
     *
     * @return array<string, array{key: string, name: string, summary: string, icon: string, modules: int}>
     */
    private function modes(): array
    {
        $locale = app()->getLocale();
        $out = [];

        foreach (config('platform-modes.modes', []) as $key => $mode) {
            $out[$key] = [
                'key' => $key,
                'name' => $mode['name'][$locale] ?? $mode['name']['ar'] ?? $key,
                'summary' => $mode['summary'][$locale] ?? $mode['summary']['ar'] ?? '',
                'icon' => $mode['icon'] ?? '◈',
                'modules' => count(array_unique($mode['modules'] ?? [])),
            ];
        }

        return $out;
    }

    /**
     * أسماء المزايا ووحداتها بلغة العرض — لتسمية ما في كل باقة
     * بلا مفاتيح تقنية في وجه الزائر.
     *
     * @return array<string, array{name: string, unit: ?string}>
     */
    private function featureNames(): array
    {
        if (! Schema::hasTable('features')) {
            return [];
        }

        $locale = app()->getLocale();

        return collect(DB::table('features')->get(['key', 'name', 'unit']))
            ->mapWithKeys(function (object $row) use ($locale): array {
                $name = json_decode((string) $row->name, true) ?: [];

                return [$row->key => [
                    'name' => $name[$locale] ?? $name['ar'] ?? $row->key,
                    'unit' => $row->unit,
                ]];
            })
            ->all();
    }
}
