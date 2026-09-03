<?php

declare(strict_types=1);

namespace App\Core\Admin;

use App\Core\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * ADR-010 / ADR-011 — تبني قائمة اللوحة من حالة المشترك الفعلية.
 *
 * ما لا يخصّ نمطه: يُخفى تماماً (لا قوائم فارغة).
 * ما تمنعه باقته:  يظهر مقفولاً مع زر ترقية — الميزة المخفية لا تُباع.
 */
final class Navigation
{
    /** @var array<string, bool>|null */
    private ?array $enabledModules = null;

    public function __construct(private readonly ?Tenant $tenant) {}

    /**
     * @return list<array{label:string, items:list<array{key:string,label:string,icon:string,locked:bool,feature:?string,url:?string}>}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (config('admin-navigation.groups') as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                $module = $item['module'] ?? null;

                // غير مفعّل لهذا النمط → لا يُعرض إطلاقاً
                if ($module !== null && ! $this->moduleEnabled($module)) {
                    continue;
                }

                $feature = $item['feature'] ?? null;
                $locked = $feature !== null && ! $this->allows($feature);

                $items[] = [
                    'key' => $item['key'],
                    'label' => __($item['label']),
                    'icon' => $item['icon'],
                    'locked' => $locked,
                    'feature' => $feature,
                    'url' => $locked ? null : $this->urlFor($item),
                ];
            }

            if ($items !== []) {
                $groups[] = ['label' => __($group['label']), 'items' => $items];
            }
        }

        return $groups;
    }

    public function isActive(string $key, string $currentKey): bool
    {
        return $key === $currentKey;
    }

    private function moduleEnabled(string $module): bool
    {
        if ($this->tenant === null) {
            return true;   // السياق المركزي يرى كل شيء
        }

        $this->enabledModules ??= DB::table('modules')
            ->where('enabled', true)
            ->pluck('enabled', 'key')
            ->map(fn ($v): bool => (bool) $v)
            ->all();

        return (bool) ($this->enabledModules[$module] ?? false);
    }

    private function allows(string $feature): bool
    {
        return $this->tenant?->allows($feature) ?? true;
    }

    private function urlFor(array $item): string
    {
        return isset($item['route']) && Route::has($item['route'])
            ? route($item['route'])
            : url('/admin/'.$item['key']);
    }
}
