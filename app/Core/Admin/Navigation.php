<?php

declare(strict_types=1);

namespace App\Core\Admin;

use App\Core\Access\Roles;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

                /*
                 | ما لا يملك صلاحيته لا يُعرض له.
                 |
                 | لا يُعرض مقفولاً كالميزة خارج الباقة: تلك تُباع
                 | بالترقية، وهذه ليست له أصلاً — وعرضها إغراء بلا باب.
                 */
                if (! $this->allowsItem($item)) {
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

    /**
     * صلاحية العنصر تُشتقّ من مورده أو تُعلَن عليه صراحةً.
     *
     * الاشتقاق مقصود: مورد يُضاف إلى القائمة يحمل حراسته معه بلا أن
     * يُذكر مرتين، فلا تتفرّق الحراسة عن الشاشة التي تحرسها.
     */
    private function allowsItem(array $item): bool
    {
        $user = $this->user();

        if ($user === null) {
            return true;   // السياق المركزي: حارس آخر ونموذج آخر
        }

        $ability = $item['ability'] ?? $this->abilityForResource($item['key']);

        return $ability === null || $this->roles()->allows($user, $ability);
    }

    private function abilityForResource(string $key): ?string
    {
        $class = config('admin-resources.tenant.'.$key);

        return $class === null ? null : app($class)->viewAbility();
    }

    private function user(): ?User
    {
        $user = $this->tenant === null ? null : Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function roles(): Roles
    {
        return app(Roles::class);
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
