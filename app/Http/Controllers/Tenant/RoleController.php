<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Access\Ability;
use App\Core\Access\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * من يملك ماذا — شاشةٌ يراها المشترك ويعدّلها.
 *
 * ## كان التوزيع في ملفٍّ لا يراه
 *
 * `config/roles.php` واحدٌ لكل المنصّة. فمركزٌ يريد موظّف الاستقبال
 * يحصّل الأقساط ولا يرى الأرباح، ومدرسةٌ تريد المدرّس يرى درجات
 * مجموعته ولا يصدّر البيانات — كلاهما كان يفتح لنا تذكرة.
 *
 * ## وصاحب المنصّة لا يُعدَّل
 *
 * لا يُعرض صفُّه ولا يُقبل تعديله. ومشتركٌ ينزع بالخطأ صلاحيةً من
 * صاحب المنصّة يُقفل نفسه خارج لوحته، ولا بابَ إلا نحن.
 *
 * ## والأسماء ثوابت
 *
 * المرونة في التوزيع لا في التعريف: لا يُقبل إلا ما يعرفه `Ability`،
 * فتعديلُ صفٍّ في القاعدة لا يخترع صلاحيةً لا يحرسها الكود.
 */
final class RoleController
{
    /** ما لا يُعرض ولا يُعدَّل */
    private const LOCKED = ['owner'];

    public function index(Request $request): View
    {
        $this->authorise($request);

        $roles = app(Roles::class);

        return view('tenant.roles', [
            'roles' => collect(array_keys((array) config('roles.abilities', [])))
                ->reject(fn (string $role): bool => in_array($role, self::LOCKED, true))
                ->mapWithKeys(fn (string $role): array => [$role => [
                    'label' => $roles->label($role),
                    'abilities' => $roles->abilitiesFor($role),
                    'default' => array_values((array) config('roles.abilities.'.$role, [])),
                ]])->all(),

            'groups' => $this->grouped(),
            'scoped' => Ability::scoped(),
        ]);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $this->authorise($request);

        abort_if(in_array($role, self::LOCKED, true), 403);
        abort_unless(array_key_exists($role, (array) config('roles.abilities', [])), 404);

        $input = $request->validate([
            'abilities' => ['array'],
            'abilities.*' => ['string'],
        ]);

        /*
         | لا يُحفَظ إلا ما يعرفه الكود.
         |
         | اسمٌ مُلفَّق في الطلب لا يفتح باباً — لا شيء يحرسه — لكنّه
         | يظهر في الشاشة صلاحيةً لا وجود لها، فيُطمأنّ إليه.
         */
        $abilities = array_values(array_intersect(
            (array) ($input['abilities'] ?? []),
            Ability::all(),
        ));

        DB::table('role_abilities')->updateOrInsert(
            ['role' => $role],
            [
                'abilities' => json_encode($abilities, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        app(Roles::class)->forgetOverrides();

        return back()->with('status', __('حُفظت صلاحيات :role.', [
            'role' => app(Roles::class)->label($role),
        ]));
    }

    /** يعيد الدور إلى ما تعرّفه المنصّة */
    public function reset(Request $request, string $role): RedirectResponse
    {
        $this->authorise($request);

        abort_if(in_array($role, self::LOCKED, true), 403);

        DB::table('role_abilities')->where('role', $role)->delete();

        app(Roles::class)->forgetOverrides();

        return back()->with('status', __('أُعيد :role إلى التوزيع الافتراضي.', [
            'role' => app(Roles::class)->label($role),
        ]));
    }

    /**
     * الصلاحيات مجموعةً بحسب ما تخصّه.
     *
     * واثنتان وخمسون صلاحيةً في قائمةٍ واحدة لا تُقرأ؛ والبادئة
     * (`courses.` و`center.`) هي التجميع الطبيعي — فلا قائمة ثانية
     * تُكتب بيدنا وتتخلّف.
     *
     * @return array<string, list<string>>
     */
    private function grouped(): array
    {
        $groups = [];

        foreach (Ability::all() as $ability) {
            $prefix = str_contains($ability, '.') ? explode('.', $ability)[0] : 'عامّ';
            $groups[$prefix][] = $ability;
        }

        ksort($groups);

        return $groups;
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::SETTINGS_MANAGE), 403);
    }
}
