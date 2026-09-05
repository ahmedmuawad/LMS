<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Modules\ModuleState;

/**
 * قائمة لوحة الطالب، مبنيّة من حالة المشترك الفعلية.
 *
 * ما لا يخصّ هذا المشترك يُخفى ولا يُعرض مقفولاً: الطالب ليس من
 * يشتري الترقية، فعرضُ ميزة عليه لا يستطيع فتحها إغراءٌ بلا باب.
 * (وهذا يخالف قائمة اللوحة الإدارية عمداً — تلك يراها من يقرّر.)
 */
final class StudentNavigation
{
    public function __construct(private readonly ModuleState $modules) {}

    /**
     * @return list<array{label:string, items:list<array{key:string,label:string,icon:string,url:string}>}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (config('student-navigation.groups', []) as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                if (! $this->visible($item)) {
                    continue;
                }

                $items[] = [
                    'key' => $item['key'],
                    'label' => __($item['label']),
                    'icon' => $item['icon'],
                    'url' => url($item['url']),
                ];
            }

            if ($items !== []) {
                $groups[] = ['label' => __($group['label']), 'items' => $items];
            }
        }

        return $groups;
    }

    /** @return list<array{key:string,label:string,icon:string,url:string}> مسطّحة — لشريط الموقع العام */
    public function flat(): array
    {
        return array_merge(...array_map(
            fn (array $group): array => $group['items'],
            $this->groups(),
        ) ?: [[]]);
    }

    /** @param array<string, mixed> $item */
    private function visible(array $item): bool
    {
        $module = $item['module'] ?? null;

        if ($module !== null && ! $this->modules->enabled($module)) {
            return false;
        }

        $feature = $item['feature'] ?? null;

        if ($feature !== null && tenant() !== null && ! tenant()->allows($feature)) {
            return false;
        }

        $setting = $item['setting'] ?? null;

        /*
         | الافتراضي هنا لا في `setting()`.
         |
         | إعدادات المشترك لا تُحفظ حتى يفتح الشاشة ويحفظ، فقيمتها
         | قبل ذلك `null`. وميزةٌ تعمل افتراضاً — كالمراجعة الذكية —
         | تختفي من قائمته إلى أن يزور شاشة إعداداتٍ لا سبب لزيارتها.
         | فيُذكَر الافتراضي مع العنصر: من أطفأها صراحةً تختفي، ومن
         | لم يمسّها تبقى.
         */
        return $setting === null
            || (bool) setting($setting, $item['setting_default'] ?? false);
    }
}
