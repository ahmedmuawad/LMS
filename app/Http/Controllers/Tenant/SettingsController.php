<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Access\Roles;
use App\Core\Settings\SettingsGroup;
use App\Core\Settings\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * شاشة واحدة لكل مجموعة إعدادات، مبنية من تعريف المجموعة نفسه.
 * إضافة إعداد جديد = سطر واحد في المجموعة، بلا شاشة ولا مسار جديد.
 */
final class SettingsController
{
    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly Roles $roles,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $first = collect($this->visibleTo($request))->first();

        // من لا يملك أي مجموعة لا يُترك أمام شاشة فارغة
        abort_if($first === null, 403, __('لا تملك صلاحية على أي إعدادات.'));

        return redirect(url('/admin/settings/'.$first->key()));
    }

    public function show(Request $request, string $group): View
    {
        $current = $this->authorised($request, $group);

        return view('tenant.settings', [
            'groups' => $this->visibleTo($request),
            'group' => $current,
            'values' => $current->values(),
            'secrets' => $this->storedSecrets($current),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $current = $this->authorised($request, $group);

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

    /**
     * المجموعة بعد التحقّق من صلاحيتها.
     *
     * الحراسة على المجموعة نفسها لا على المسار: `payments` تحتاج ما
     * لا تحتاجه `general`، ومسار واحد يخدمهما جميعاً.
     */
    private function authorised(Request $request, string $group): SettingsGroup
    {
        $current = $this->registry->resolve($group);

        if (! $this->roles->allows($request->user(), $current->ability())) {
            throw new AccessDeniedHttpException(__('لا تملك صلاحية على هذه المجموعة.'));
        }

        return $current;
    }

    /** @return list<SettingsGroup> */
    private function visibleTo(Request $request): array
    {
        return array_values(array_filter(
            $this->registry->visible(),
            fn (SettingsGroup $group): bool => $this->roles->allows($request->user(), $group->ability()),
        ));
    }
}
