<?php

declare(strict_types=1);

use App\Core\Access\Ability;
use App\Core\Access\Roles;
use App\Models\User;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;

/*
| توزيع الصلاحيات لكل مشترك.
|
| وأخطر ما هنا ليس الشاشة: `Roles` مفردةٌ في الحاوية، ولمّا صارت
| تقرأ من قاعدة المشترك صارت تحمل توزيع أوّل مشترك مرّ بها إلى كل
| من بعده — فتُمنح صلاحيةُ موظّفٍ عند مشترك لموظّفٍ عند غيره. ولا
| يظهر ذلك في طلبٍ عادي، بل في الطابور والأوامر والاختبارات.
*/

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);

    $this->tenant = provision();
});

function overrideRole(string $role, array $abilities): void
{
    DB::table('role_abilities')->updateOrInsert(
        ['role' => $role],
        [
            'abilities' => json_encode($abilities, JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ],
    );

    app(Roles::class)->forgetOverrides();
}

it('uses the platform defaults when the tenant changed nothing', function () {
    $this->tenant->run(function (): void {
        expect(app(Roles::class)->abilitiesFor('admin'))
            ->toBe(array_values((array) config('roles.abilities.admin', [])));
    });
});

it('lets a tenant narrow a role to exactly what it chose', function () {
    $this->tenant->run(function (): void {
        // مركزٌ يريد الاستقبال يحصّل الأقساط ولا يرى الأرباح
        overrideRole('staff', [Ability::FEES_COLLECT]);

        $abilities = app(Roles::class)->abilitiesFor('staff');

        expect($abilities)->toBe([Ability::FEES_COLLECT])
            ->and($abilities)->not->toContain(Ability::EARNINGS_VIEW);
    });
});

it('keeps the owner holding everything, whatever a row says', function () {
    $this->tenant->run(function (): void {
        /*
         | صفٌّ يحاول تقليم صاحب المنصّة يُتجاهَل.
         |
         | ومشتركٌ ينزع بالخطأ صلاحيةً منه يُقفل نفسه خارج لوحته،
         | ولا بابَ إلا نحن.
         */
        overrideRole('owner', [Ability::FEES_COLLECT]);

        $owner = User::factory()->create(['role' => 'owner', 'status' => 'active']);

        expect(app(Roles::class)->abilitiesFor('owner'))->toBe(Ability::all())
            ->and(app(Roles::class)->allows($owner, Ability::SETTINGS_MANAGE))->toBeTrue();
    });
});

it('ignores an ability name the code does not define', function () {
    $this->tenant->run(function (): void {
        overrideRole('staff', [Ability::FEES_COLLECT, 'invented.ability']);

        // لا تفتح باباً — لا شيء يحرسها — لكنّها تُظهر صلاحيةً لا وجود لها
        expect(app(Roles::class)->abilitiesFor('staff'))->toBe([Ability::FEES_COLLECT]);
    });
});

it('does not leak one tenant distribution into another', function () {
    $other = provision(['name' => 'أكاديمية أخرى', 'owner_email' => 'other@example.test']);

    $this->tenant->run(fn () => overrideRole('staff', [Ability::FEES_COLLECT]));

    $other->run(function (): void {
        /*
         | وهذا ما يحرسه `ScopedStateBootstrapper`.
         |
         | بلاه تحمل المفردة توزيع أوّل مشترك إلى كل من بعده — في
         | الطابور حيث يمرّ مشتركان في عمليةٍ واحدة.
         */
        expect(app(Roles::class)->abilitiesFor('staff'))
            ->toBe(array_values((array) config('roles.abilities.staff', [])));
    });
});

it('actually gates a user by the tenant distribution', function () {
    $this->tenant->run(function (): void {
        overrideRole('staff', [Ability::FEES_COLLECT]);

        $staff = User::factory()->create(['role' => 'staff', 'status' => 'active']);

        expect(app(Roles::class)->allows($staff, Ability::FEES_COLLECT))->toBeTrue()
            ->and(app(Roles::class)->allows($staff, Ability::SETTINGS_MANAGE))->toBeFalse();
    });
});
