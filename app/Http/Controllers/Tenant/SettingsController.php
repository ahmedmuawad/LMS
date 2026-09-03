<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Settings\SettingsGroup;
use App\Core\Settings\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * شاشة واحدة لكل مجموعة إعدادات، مبنية من تعريف المجموعة نفسه.
 * إضافة إعداد جديد = سطر واحد في المجموعة، بلا شاشة ولا مسار جديد.
 */
final class SettingsController
{
    public function __construct(private readonly SettingsRegistry $registry) {}

    public function index(): RedirectResponse
    {
        return redirect(url('/admin/settings/'.$this->registry->first()->key()));
    }

    public function show(string $group): View
    {
        $current = $this->registry->resolve($group);

        return view('tenant.settings', [
            'groups' => $this->registry->visible(),
            'group' => $current,
            'values' => $current->values(),
            'secrets' => $this->storedSecrets($current),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $current = $this->registry->resolve($group);

        $validated = $request->validate(
            $current->validationRules(),
            [],
            $current->validationAttributes(),
        );

        $secrets = $current->secretFields();

        foreach ($current->fields() as $field) {
            $name = $field->name;
            $value = $validated[$name] ?? null;

            // الفراغ في حقل سرّي يعني «أبقِ القائم»، لا «امحُ ما هو محفوظ»
            if ($field->shouldSkipWhenEmpty() && ($value === null || $value === '')) {
                continue;
            }

            setting()->set(
                $current->key().'.'.$name,
                $field->fill($value),
                encrypted: in_array($name, $secrets, true),
            );
        }

        return redirect(url('/admin/settings/'.$current->key()))
            ->with('status', __('تم حفظ إعدادات :group.', ['group' => $current->label()]));
    }

    /** @return array<string, bool> أي الأسرار محفوظ فعلاً — لنقول ذلك بلا كشفه */
    private function storedSecrets(SettingsGroup $group): array
    {
        $stored = setting()->group($group->key());
        $out = [];

        foreach ($group->secretFields() as $name) {
            $out[$name] = filled($stored[$name] ?? null);
        }

        return $out;
    }
}
