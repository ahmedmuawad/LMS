<?php

declare(strict_types=1);

namespace App\Core\Settings;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * سجلّ شاشات الإعدادات.
 *
 * يُخفي ما لا يخصّ نمط المشترك: شاشة إعدادات لموديول معطّل هي
 * وعد بميزة غير موجودة، وهذا أسوأ من غيابها.
 */
final class SettingsRegistry
{
    /** @var array<string, bool>|null */
    private ?array $modules = null;

    /** @return list<SettingsGroup> */
    public function visible(): array
    {
        $groups = [];

        foreach (array_keys(config('settings-groups', [])) as $key) {
            $group = $this->make($key);

            if ($group->module() === null || $this->moduleEnabled($group->module())) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    public function first(): SettingsGroup
    {
        return $this->visible()[0] ?? $this->make(array_key_first(config('settings-groups', [])));
    }

    /** يرفض المجموعة المخفية كما يرفض غير الموجودة — لا فرق من الخارج. */
    public function resolve(string $key): SettingsGroup
    {
        foreach ($this->visible() as $group) {
            if ($group->key() === $key) {
                return $group;
            }
        }

        throw new NotFoundHttpException("مجموعة إعدادات غير معروفة: [{$key}]");
    }

    private function make(string $key): SettingsGroup
    {
        $class = config("settings-groups.{$key}")
            ?? throw new NotFoundHttpException("مجموعة إعدادات غير معروفة: [{$key}]");

        return app($class);
    }

    private function moduleEnabled(string $module): bool
    {
        if (! tenancy()->initialized) {
            return true;
        }

        $this->modules ??= DB::table('modules')->where('enabled', true)
            ->pluck('enabled', 'key')->map(fn ($v): bool => (bool) $v)->all();

        return (bool) ($this->modules[$module] ?? false);
    }
}
