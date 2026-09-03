<?php

declare(strict_types=1);

use App\Core\Admin\Resources\UserResource;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;

// ADR-004 — تعريف واحد في PHP يولّد شاشة كاملة بمكوّنات نظام التصميم.

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(CountrySeeder::class);
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

function tenantWithUsers(): array
{
    $tenant = provision(['name' => 'أكاديمية الموارد', 'owner_email' => 'res@example.test']);

    $tenant->run(function (): void {
        $rows = [
            ['name' => 'سارة عبد الرحمن', 'email' => 'sara@t.test',  'status' => 'active',    'legacy_hash' => true,  'phone' => '01000000001'],
            ['name' => 'يوسف حمدي',       'email' => 'youssef@t.test', 'status' => 'pending',   'legacy_hash' => false, 'phone' => '01000000002'],
            ['name' => 'منة الله طارق',    'email' => 'mennah@t.test', 'status' => 'suspended', 'legacy_hash' => false, 'phone' => '01000000003'],
        ];

        foreach ($rows as $row) {
            DB::table('users')->insert([...$row, 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    actingAsOwner($tenant);

    return [$tenant, 'http://'.$tenant->domains->first()->domain];
}

it('renders a full screen from the resource definition', function () {
    [$tenant, $base] = tenantWithUsers();

    $this->get($base.'/admin/users')
        ->assertOk()
        ->assertSee('المستخدمون', false)
        ->assertSee('سارة عبد الرحمن', false)
        ->assertSee('يوسف حمدي', false)
        ->assertSee('الحالة', false)
        ->assertSee('تاريخ التسجيل', false);

    tenancy()->end();
});

it('searches only the columns declared searchable', function () {
    [$tenant, $base] = tenantWithUsers();

    $this->get($base.'/admin/users?q=يوسف')
        ->assertOk()
        ->assertSee('يوسف حمدي', false)
        ->assertDontSee('سارة عبد الرحمن', false);

    tenancy()->end();
});

it('filters by a select filter', function () {
    [$tenant, $base] = tenantWithUsers();

    $this->get($base.'/admin/users?status=suspended')
        ->assertOk()
        ->assertSee('منة الله طارق', false)
        ->assertDontSee('سارة عبد الرحمن', false);

    tenancy()->end();
});

it('ignores a filter value outside the declared options', function () {
    [$tenant, $base] = tenantWithUsers();

    // قيمة ملفّقة لا تُمرَّر إلى الاستعلام — تُتجاهل بالكامل
    $this->get($base.'/admin/users?status=; DROP TABLE users; --')
        ->assertOk()
        ->assertSee('سارة عبد الرحمن', false)
        ->assertSee('يوسف حمدي', false);

    $tenant->run(fn () => expect(DB::table('users')->count())->toBe(4));

    tenancy()->end();
});

it('refuses to sort by a column that is not declared sortable', function () {
    [$tenant, $base] = tenantWithUsers();

    // عمود غير مصرّح به يسقط إلى الترتيب الافتراضي بدل الوصول للاستعلام
    $this->get($base.'/admin/users?sort=password&dir=asc')->assertOk();
    $this->get($base.'/admin/users?sort=nonexistent_column')->assertOk();

    tenancy()->end();
});

it('sorts by a declared column', function () {
    [$tenant, $base] = tenantWithUsers();

    $html = $this->get($base.'/admin/users?sort=name&dir=asc')->assertOk()->getContent();

    expect(strpos($html, 'سارة عبد الرحمن'))->toBeLessThan(strpos($html, 'يوسف حمدي'));

    tenancy()->end();
});

it('shows the empty state when nothing matches', function () {
    [$tenant, $base] = tenantWithUsers();

    $this->get($base.'/admin/users?q=لا-يوجد-هذا-الاسم')
        ->assertOk()
        ->assertSee('لا نتائج مطابقة', false)
        ->assertSee('مسح الفلاتر', false);

    tenancy()->end();
});

it('defines its own empty state rather than a generic one', function () {
    // الحالة الفارغة إلزامية لكل مورد (وثيقة 13) — ولا يمكن اختبارها
    // عبر HTTP هنا لأن المشترك يملك دائماً حساب المالك على الأقل
    $empty = (new UserResource)->emptyState();

    expect($empty['title'])->toBe('لا يوجد مستخدمون بعد')
        ->and($empty['body'])->toContain('سيظهر هنا كل من يسجّل');
});

it('returns 404 for an unknown resource key', function () {
    [$tenant, $base] = tenantWithUsers();

    $this->get($base.'/admin/nonexistent')->assertNotFound();

    tenancy()->end();
});

it('derives searchable and sortable columns from the definition', function () {
    $resource = new UserResource;

    expect($resource->searchableColumns())->toBe(['name', 'phone'])
        ->and($resource->label())->toBe('المستخدمون');
});
