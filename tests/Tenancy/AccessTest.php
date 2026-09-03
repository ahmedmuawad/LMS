<?php

declare(strict_types=1);

use App\Core\Access\Ability;
use App\Core\Access\Roles;
use App\Core\Admin\Navigation;
use App\Models\User;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Instructor;
use App\Modules\Lms\Models\Lesson;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

/*
 | الصلاحيات وحصر النطاق.
 |
 | هذا الملف هو ما كان غائباً: ٤٨٩ اختباراً كانت تحرس ما بُني، ولا
 | تسأل قطّ «ماذا يستطيع من لا يجوز أن يستطيع؟».
 */

/** يُنشئ مدرّساً بحساب وسجلّ مدرّس وكورس يملكه. */
function makeInstructor(string $email = 'teacher@t.test'): array
{
    $user = User::create([
        'name' => 'مدرّس', 'email' => $email, 'password' => 'password',
        'role' => 'instructor', 'status' => 'active',
    ]);

    $instructor = Instructor::create(['user_id' => $user->getKey(), 'approved_at' => now()]);

    return [$user, $instructor];
}

// ------------------------------------------------------------------
// الحكم نفسه
// ------------------------------------------------------------------

it('يمنح صاحب المنصّة كل صلاحية بلا تعداد', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $owner = User::where('role', 'owner')->firstOrFail();
        $roles = app(Roles::class);

        foreach (Ability::all() as $ability) {
            expect($roles->allows($owner, $ability))->toBeTrue("صاحب المنصّة يجب أن يملك [{$ability}]");
        }
    });
});

it('يمنع المدير من فوترة الاشتراك وحدها', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $admin = User::create([
            'name' => 'مدير', 'email' => 'admin@t.test', 'password' => 'password',
            'role' => 'admin', 'status' => 'active',
        ]);

        $roles = app(Roles::class);

        expect($roles->allows($admin, Ability::BILLING_MANAGE))->toBeFalse()
            ->and($roles->allows($admin, Ability::SETTINGS_MANAGE))->toBeTrue()
            ->and($roles->allows($admin, Ability::USERS_MANAGE))->toBeTrue();
    });
});

it('يمنع الحساب الموقوف من كل شيء ولو كان صاحب المنصّة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $owner = User::where('role', 'owner')->firstOrFail();
        $owner->forceFill(['status' => 'suspended'])->save();

        $roles = app(Roles::class);

        expect($roles->allows($owner->refresh(), Ability::DASHBOARD))->toBeFalse()
            ->and($roles->mayEnterPanel($owner))->toBeFalse();
    });
});

it('لا يدخل الطالب ولا ولي الأمر اللوحة', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $roles = app(Roles::class);

        foreach (['student', 'guardian'] as $role) {
            $user = User::create([
                'name' => $role, 'email' => $role.'@t.test', 'password' => 'password',
                'role' => $role, 'status' => 'active',
            ]);

            expect($roles->mayEnterPanel($user))->toBeFalse()
                ->and($roles->allows($user, Ability::DASHBOARD))->toBeFalse();
        }
    });
});

// ------------------------------------------------------------------
// ما لا يستطيعه المدرّس — الثغرة التي كانت مفتوحة
// ------------------------------------------------------------------

it('يمنع المدرّس من كل شاشة لا تخصّه', function (): void {
    $tenant = provision();

    $tenant->run(fn () => makeInstructor());

    tenancy()->initialize($tenant);
    test()->actingAs($tenant->run(fn () => User::where('email', 'teacher@t.test')->firstOrFail()));

    // كانت كلّها تُعيد 200 قبل هذه الطبقة
    $forbidden = [
        '/admin/orders',
        '/admin/users',
        '/admin/products',
        '/admin/coupons',
        '/admin/recharge-codes',
        '/admin/recharge-codes/generate',
        '/admin/refunds',
        '/admin/billing',
        '/admin/settings/payments',
        '/admin/settings/integrations',
        '/admin/settings/security',
        '/admin/affiliates',
        '/admin/campaigns',
        '/admin/notifications',
        '/admin/media',
        '/admin/posts',
        '/admin/pages',
        '/admin/page-builder',
        '/admin/comments',
        '/admin/forms',
        '/admin/redirects',
        '/admin/badges',
        '/admin/taxonomies',
        '/admin/groups',
        '/admin/branches',
        '/admin/rooms',
        '/admin/attendance',
        '/admin/fees',
        '/admin/cashboxes',
    ];

    foreach ($forbidden as $path) {
        $response = tenantGet($tenant, $path);

        expect($response->getStatusCode())->toBeIn([403, 404], "المدرّس لا يجوز أن يفتح [{$path}]");
    }
});

it('يمنع المدرّس من كتابة إعدادات المدفوعات', function (): void {
    $tenant = provision();

    $tenant->run(fn () => makeInstructor());

    tenancy()->initialize($tenant);
    test()->actingAs($tenant->run(fn () => User::where('email', 'teacher@t.test')->firstOrFail()));

    // القراءة ممنوعة، والكتابة كذلك — وهي الأخطر: تُحوّل الدخل بصمت
    tenantPut($tenant, '/admin/settings/payments', ['paymob_api_key' => 'مفتاح-المهاجم'])
        ->assertForbidden();
});

it('يسمح للمدرّس بما يخصّه', function (): void {
    $tenant = provision();

    $tenant->run(fn () => makeInstructor());

    tenancy()->initialize($tenant);
    test()->actingAs($tenant->run(fn () => User::where('email', 'teacher@t.test')->firstOrFail()));

    foreach (['/admin/dashboard', '/admin/courses', '/admin/lessons', '/admin/quizzes',
        '/admin/questions', '/admin/enrollments', '/admin/certificates',
        '/admin/grading', '/admin/services', '/admin/bookings', '/admin/reviews'] as $path) {
        tenantGet($tenant, $path)->assertOk();
    }
});

// ------------------------------------------------------------------
// حصر النطاق — الصلاحية بلا حصر بلا معنى
// ------------------------------------------------------------------

it('لا يرى المدرّس إلا كورساته', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        [, $mine] = makeInstructor();
        [, $theirs] = makeInstructor('other@t.test');

        seedCourse(['slug' => 'my-course', 'instructor_id' => $mine->getKey()]);
        seedCourse(['slug' => 'their-course', 'instructor_id' => $theirs->getKey()]);
    });

    tenancy()->initialize($tenant);
    test()->actingAs($tenant->run(fn () => User::where('email', 'teacher@t.test')->firstOrFail()));

    tenantGet($tenant, '/admin/courses')
        ->assertOk()
        ->assertSee('my-course')
        ->assertDontSee('their-course');
});

it('يعيد 404 لا 403 على كورس غيره — لا يُخبره أنه موجود', function (): void {
    $tenant = provision();

    $foreign = $tenant->run(function (): int {
        makeInstructor();
        [, $theirs] = makeInstructor('other@t.test');

        return (int) seedCourse(['slug' => 'their-course', 'instructor_id' => $theirs->getKey()])->getKey();
    });

    tenancy()->initialize($tenant);
    test()->actingAs($tenant->run(fn () => User::where('email', 'teacher@t.test')->firstOrFail()));

    tenantGet($tenant, '/admin/courses/'.$foreign.'/edit')->assertNotFound();
    tenantPut($tenant, '/admin/courses/'.$foreign, ['title' => ['ar' => 'اختُرِق']])->assertNotFound();
    tenantDelete($tenant, '/admin/courses/'.$foreign)->assertNotFound();

    $tenant->run(fn () => expect(Course::find($foreign)->slug)->toBe('their-course'));
});

it('لا يرى المدرّس بنك دروس غيره', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        [$mine] = makeInstructor();
        [$theirs] = makeInstructor('other@t.test');

        Lesson::create(['title' => ['ar' => 'درسي'], 'type' => 'video', 'created_by' => $mine->getKey()]);
        Lesson::create(['title' => ['ar' => 'درس غيري'], 'type' => 'video', 'created_by' => $theirs->getKey()]);
    });

    tenancy()->initialize($tenant);
    test()->actingAs($tenant->run(fn () => User::where('email', 'teacher@t.test')->firstOrFail()));

    tenantGet($tenant, '/admin/lessons')
        ->assertOk()
        ->assertSee('درسي')
        ->assertDontSee('درس غيري');
});

it('يثبّت مُنشِئ الدرس تلقائياً بلا تمريره', function (): void {
    $tenant = provision();

    $tenant->run(fn () => makeInstructor());

    tenancy()->initialize($tenant);
    $teacher = $tenant->run(fn () => User::where('email', 'teacher@t.test')->firstOrFail());
    test()->actingAs($teacher);

    tenantPost($tenant, '/admin/lessons', [
        'title' => ['ar' => 'درس جديد'], 'type' => 'video',
    ])->assertRedirect();

    $tenant->run(fn () => expect((int) Lesson::latest('id')->first()->created_by)->toBe((int) $teacher->getKey()));
});

it('يرى المدرّس بلا كورسات لا شيء لا الكل', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        makeInstructor();
        [, $theirs] = makeInstructor('other@t.test');

        seedCourse(['slug' => 'their-course', 'instructor_id' => $theirs->getKey()]);
    });

    tenancy()->initialize($tenant);
    test()->actingAs($tenant->run(fn () => User::where('email', 'teacher@t.test')->firstOrFail()));

    // الحصر الفارغ يعني «لا صفوف» لا «تخطَّ الشرط»
    tenantGet($tenant, '/admin/enrollments')->assertOk()->assertDontSee('their-course');
});

// ------------------------------------------------------------------
// موظّف السنتر
// ------------------------------------------------------------------

it('يفتح موظّف السنتر تشغيله ولا يفتح المال ولا الإعدادات', function (): void {
    $tenant = provision(['platform_mode' => 'center', 'plan_key' => 'center']);

    $tenant->run(fn () => User::create([
        'name' => 'موظّف', 'email' => 'staff@t.test', 'password' => 'password',
        'role' => 'staff', 'status' => 'active',
    ]));

    tenancy()->initialize($tenant);
    test()->actingAs($tenant->run(fn () => User::where('email', 'staff@t.test')->firstOrFail()));

    foreach (['/admin/dashboard', '/admin/attendance', '/admin/fees', '/admin/cashboxes'] as $path) {
        tenantGet($tenant, $path)->assertOk();
    }

    foreach (['/admin/settings/payments', '/admin/billing', '/admin/affiliates',
        '/admin/reports', '/admin/recharge-codes'] as $path) {
        expect(tenantGet($tenant, $path)->getStatusCode())->toBeIn([403, 404], $path);
    }
});

// ------------------------------------------------------------------
// حرّاس البنية — يمنعون رجوع الثغرة
// ------------------------------------------------------------------

it('كل مورد يُعلن صلاحيته', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        foreach (config('admin-resources.tenant') as $key => $class) {
            $ability = app($class)->viewAbility();

            expect($ability)->toBeIn(Ability::all(), "المورد [{$key}] يُعلن صلاحية غير معرَّفة");
        }
    });
});

it('كل مجموعة إعدادات تُعلن صلاحيتها', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        foreach (config('settings-groups') as $key => $class) {
            expect(app($class)->ability())->toBeIn(Ability::all(), "المجموعة [{$key}] تُعلن صلاحية غير معرَّفة");
        }
    });
});

/*
 | الحارس الأهم: كل رابط تعرضه القائمة يجب أن يفتح.
 |
 | غيابه هو ما أخفى ١٥ رابطاً مكسوراً في القوائم عن ٤٨٩ اختباراً:
 | الاختبار يحرس ما بُني، ولا يعرف شيئاً عن شاشة غائبة تربطها قائمة.
 */
it('لا رابط مكسور في قائمة المشترك', function (): void {
    foreach ([['marketplace', 'growth'], ['center', 'center']] as [$mode, $plan]) {
        $tenant = provision(['platform_mode' => $mode, 'plan_key' => $plan]);

        actingAsOwner($tenant);

        $items = $tenant->run(fn (): array => collect(app(Navigation::class)->groups())
            ->flatMap(fn (array $group): array => $group['items'])
            ->reject(fn (array $item): bool => $item['locked'] || $item['url'] === null)
            ->all());

        expect($items)->not->toBeEmpty();

        foreach ($items as $item) {
            $path = parse_url($item['url'], PHP_URL_PATH) ?: '/';

            // نتبع التحويل: الرابط يجب أن **ينتهي** إلى شاشة تعمل،
            // وتحويلٌ إلى 404 رابطٌ مكسور مهما بدا سليماً في أوّله
            $status = test()->followingRedirects()
                ->get(tenantUrl($tenant, $path))
                ->getStatusCode();

            expect($status)->toBe(200, "رابط مكسور في نمط [{$mode}]: {$item['label']} → {$path}");
        }
    }
});
