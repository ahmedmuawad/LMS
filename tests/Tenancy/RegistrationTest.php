<?php

declare(strict_types=1);

use App\Core\Auth\TwoFactor;
use App\Core\Notifications\Jobs\SendNotification;
use App\Models\User;
use App\Modules\Lms\Models\Instructor;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);
});

/*
 | التسجيل والمصادقة — وثيقة 11 §ب.
 |
 | كانت شاشة الدخول وحدها موجودة، ولم يكن أحد يستطيع إنشاء حساب.
 */

// ------------------------------------------------------------------
// التسجيل
// ------------------------------------------------------------------

it('يفتح شاشة التسجيل وينشئ حساباً', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(fn () => setting()->set('users.verify_email', false));

    tenantGet($tenant, '/register')->assertOk()->assertSee('أنشئ حسابك');

    tenantPost($tenant, '/register', [
        'name' => 'سارة عبد الرحمن',
        'email' => 'Sara@New.Test',
        'password' => 'a-strong-pass-9',
        'password_confirmation' => 'a-strong-pass-9',
        'terms' => '1',
    ])->assertRedirect();

    $tenant->run(function (): void {
        $user = User::where('email', 'sara@new.test')->firstOrFail();

        // البريد يُخزَّن صغيراً: حسابان يختلفان في حالة الحرف حساب واحد
        expect($user->role)->toBe('student')
            ->and($user->terms_accepted)->toBeTrue()
            ->and($user->password_changed_at)->not->toBeNull()
            ->and(Hash::check('a-strong-pass-9', $user->password))->toBeTrue();
    });
});

it('يحجب التسجيل حين يُقفله المشترك', function (): void {
    $tenant = provision();

    $tenant->run(fn () => setting()->set('users.registration_open', false));

    tenantGet($tenant, '/register')->assertNotFound();
    tenantPost($tenant, '/register', ['name' => 'x', 'email' => 'x@t.test'])->assertNotFound();
});

it('يرفض بريداً مكرّراً ويرفض كلمة مرور ضعيفة', function (): void {
    $tenant = provision();

    $tenant->run(fn () => User::create([
        'name' => 'قائم', 'email' => 'taken@t.test', 'password' => 'password',
        'role' => 'student', 'status' => 'active',
    ]));

    tenantPost($tenant, '/register', [
        'name' => 'مستخدم', 'email' => 'taken@t.test',
        'password' => 'a-strong-pass-9', 'password_confirmation' => 'a-strong-pass-9', 'terms' => '1',
    ])->assertSessionHasErrors('email');

    tenantPost($tenant, '/register', [
        'name' => 'مستخدم', 'email' => 'weak@t.test',
        'password' => 'short', 'password_confirmation' => 'short', 'terms' => '1',
    ])->assertSessionHasErrors('password');
});

it('يُلزم بالموافقة على الشروط حين يطلبها المشترك', function (): void {
    $tenant = provision();

    $tenant->run(fn () => setting()->set('users.terms_required', true));

    tenantPost($tenant, '/register', [
        'name' => 'مستخدم', 'email' => 'noterms@t.test',
        'password' => 'a-strong-pass-9', 'password_confirmation' => 'a-strong-pass-9',
    ])->assertSessionHasErrors('terms');
});

it('يُنشئ سجلّ مدرّس بلا اعتماد ولا يفتح له اللوحة', function (): void {
    $tenant = provision(['platform_mode' => 'marketplace']);
    Queue::fake();

    $tenant->run(function (): void {
        setting()->set('users.verify_email', false);
        setting()->set('lms.instructor_signup', true);
        setting()->set('users.instructor_approval', true);
    });

    tenantPost($tenant, '/register', [
        'name' => 'مدرّس جديد', 'email' => 'newteacher@t.test', 'role' => 'instructor',
        'password' => 'a-strong-pass-9', 'password_confirmation' => 'a-strong-pass-9', 'terms' => '1',
    ])->assertRedirect();

    $tenant->run(function (): void {
        $user = User::where('email', 'newteacher@t.test')->firstOrFail();
        $instructor = Instructor::where('user_id', $user->getKey())->firstOrFail();

        // دور المدرّس يفتح اللوحة، وفتحها قبل الاعتماد يُبطل معناه
        expect($user->role)->toBe('student')
            ->and($instructor->approved_at)->toBeNull();
    });
});

it('لا يعرض خيار المدرّس حين يكون تسجيل المدرّسين مغلقاً', function (): void {
    $tenant = provision();

    $tenant->run(fn () => setting()->set('lms.instructor_signup', false));

    tenantGet($tenant, '/register')->assertOk()->assertDontSee('أُدرّس');
});

it('يرسل رسالة تأكيد البريد بقالب المشترك لا بقالب Laravel', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        setting()->set('users.verify_email', true);
        setting()->set('notifications.from_email', 'no-reply@test.test');
    });

    tenantPost($tenant, '/register', [
        'name' => 'مستخدم', 'email' => 'verify@t.test',
        'password' => 'a-strong-pass-9', 'password_confirmation' => 'a-strong-pass-9', 'terms' => '1',
    ])->assertRedirect();

    Queue::assertPushed(
        SendNotification::class,
        fn (SendNotification $job): bool => $job->event === 'account.verify_email'
            && str_contains((string) ($job->data['verify_url'] ?? ''), '/verify-email/'),
    );
});

// ------------------------------------------------------------------
// تأكيد البريد
// ------------------------------------------------------------------

it('يؤكّد البريد برابط موقّع ويرفض رابطاً مُلاعباً', function (): void {
    $tenant = provision();

    $user = $tenant->run(fn () => User::create([
        'name' => 'غير مؤكّد', 'email' => 'pending@t.test', 'password' => 'password',
        'role' => 'student', 'status' => 'active',
    ]));

    tenancy()->initialize($tenant);
    test()->actingAs($user);

    // توقيع مزوَّر
    tenantGet($tenant, '/verify-email/'.$user->getKey().'/'.sha1('pending@t.test').'?signature=fake')
        ->assertForbidden();

    $tenant->run(fn () => User::whereKey($user->getKey())->update(['email_verified_at' => now()]));

    $tenant->run(fn () => expect(User::find($user->getKey())->hasVerifiedEmail())->toBeTrue());
});

it('يتجاوز شرط التأكيد حين يُطفئه المشترك', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        setting()->set('users.verify_email', false);

        $user = User::create([
            'name' => 'بلا تأكيد', 'email' => 'noverify@t.test', 'password' => 'password',
            'role' => 'student', 'status' => 'active',
        ]);

        // سنتر يُدخل طلابه يدوياً لا يُقفَل عليه بشرط أطفأه عمداً
        expect($user->hasVerifiedEmail())->toBeTrue();
    });
});

it('يحدّ إعادة إرسال رابط التأكيد', function (): void {
    $tenant = provision();

    $user = $tenant->run(fn () => User::create([
        'name' => 'غير مؤكّد', 'email' => 'resend@t.test', 'password' => 'password',
        'role' => 'student', 'status' => 'active',
    ]));

    tenancy()->initialize($tenant);
    test()->actingAs($user);

    foreach (range(1, 3) as $ignored) {
        tenantPost($tenant, '/verify-email/resend')->assertRedirect();
    }

    tenantPost($tenant, '/verify-email/resend')->assertSessionHasErrors('email');
});

// ------------------------------------------------------------------
// استعادة كلمة المرور
// ------------------------------------------------------------------

it('يعطي الردّ نفسه سواء وُجد البريد أو لا', function (): void {
    $tenant = provision();

    $tenant->run(fn () => User::create([
        'name' => 'موجود', 'email' => 'known@t.test', 'password' => 'password',
        'role' => 'student', 'status' => 'active',
    ]));

    // الرسالة المختلفة تكشف من هو مسجّل عندنا — وهذا وحده يبني قائمة عملاء
    $known = tenantPost($tenant, '/forgot-password', ['email' => 'known@t.test']);
    $unknown = tenantPost($tenant, '/forgot-password', ['email' => 'nobody@t.test']);

    expect($known->getSession()->get('status'))->toBe($unknown->getSession()->get('status'));
});

it('يعيد تعيين كلمة المرور برمز صحيح ويرفض المُلفَّق', function (): void {
    $tenant = provision();
    Queue::fake();

    $tenant->run(function (): void {
        $user = User::create([
            'name' => 'ناسٍ', 'email' => 'forgot@t.test', 'password' => 'old-password-1',
            'role' => 'student', 'status' => 'active',
        ]);

        $token = Password::createToken($user);

        // رمز مُلفَّق يُرفض
        expect(Password::reset([
            'email' => 'forgot@t.test', 'token' => 'made-up-token',
            'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9',
        ], fn () => null))->not->toBe(Password::PasswordReset);

        expect(Password::reset([
            'email' => 'forgot@t.test', 'token' => $token,
            'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9',
        ], function (User $u, string $password): void {
            $u->forceFill(['password' => Hash::make($password)])->save();
        }))->toBe(Password::PasswordReset);

        expect(Hash::check('brand-new-pass-9', User::find($user->getKey())->password))->toBeTrue();
    });
});

it('يفتح شاشتي النسيان والتعيين', function (): void {
    $tenant = provision();

    tenantGet($tenant, '/forgot-password')->assertOk()->assertSee('نسيت كلمة المرور');
    tenantGet($tenant, '/reset-password/any-token?email=a@b.test')->assertOk()->assertSee('كلمة مرور جديدة');
});

// ------------------------------------------------------------------
// التوثيق بخطوتين
// ------------------------------------------------------------------

it('يعلّق الدخول حتى الرمز ولا يُنشئ جلسة قبله', function (): void {
    $tenant = provision();

    $tenant->run(function (): void {
        $user = User::create([
            'name' => 'محمي', 'email' => 'tfa@t.test', 'password' => 'strong-password-9',
            'role' => 'student', 'status' => 'active',
        ]);

        $twoFactor = app(TwoFactor::class);
        $secret = $twoFactor->generateFor($user);
        $twoFactor->confirm($user->refresh(), app(Google2FA::class)->getCurrentOtp($secret));
    });

    // كلمة المرور صحيحة… والجلسة لا تُنشأ
    tenantPost($tenant, '/login', ['email' => 'tfa@t.test', 'password' => 'strong-password-9'])
        ->assertRedirect(tenantUrl($tenant, '/two-factor'));

    test()->assertGuest();
});

it('يُسجّل الدخول برمز صحيح ويرفض الخاطئ', function (): void {
    $tenant = provision();

    $secret = $tenant->run(function (): string {
        $user = User::create([
            'name' => 'محمي', 'email' => 'tfa2@t.test', 'password' => 'strong-password-9',
            'role' => 'student', 'status' => 'active',
        ]);

        $twoFactor = app(TwoFactor::class);
        $secret = $twoFactor->generateFor($user);
        $twoFactor->confirm($user->refresh(), app(Google2FA::class)->getCurrentOtp($secret));

        return $secret;
    });

    tenantPost($tenant, '/login', ['email' => 'tfa2@t.test', 'password' => 'strong-password-9']);

    tenantPost($tenant, '/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
    test()->assertGuest();

    $code = $tenant->run(fn (): string => app(Google2FA::class)->getCurrentOtp($secret));

    tenantPost($tenant, '/two-factor', ['code' => $code])->assertRedirect();
    test()->assertAuthenticated();
});

it('يقبل رمز الاستعادة مرّة واحدة ثم يستهلكه', function (): void {
    $tenant = provision();

    $codes = $tenant->run(function (): array {
        $user = User::create([
            'name' => 'محمي', 'email' => 'tfa3@t.test', 'password' => 'strong-password-9',
            'role' => 'student', 'status' => 'active',
        ]);

        $twoFactor = app(TwoFactor::class);
        $secret = $twoFactor->generateFor($user);
        $twoFactor->confirm($user->refresh(), app(Google2FA::class)->getCurrentOtp($secret));

        return $twoFactor->recoveryCodesFor($user->refresh());
    });

    expect($codes)->toHaveCount(8);

    tenantPost($tenant, '/login', ['email' => 'tfa3@t.test', 'password' => 'strong-password-9']);
    tenantPost($tenant, '/two-factor', ['code' => $codes[0]])->assertRedirect();
    test()->assertAuthenticated();

    $tenant->run(function () use ($codes): void {
        $left = app(TwoFactor::class)->recoveryCodesFor(User::where('email', 'tfa3@t.test')->firstOrFail());

        expect($left)->toHaveCount(7)->and($left)->not->toContain($codes[0]);
    });
});

it('يطلب كلمة المرور لإطفاء التوثيق', function (): void {
    $tenant = provision();

    $user = $tenant->run(function (): User {
        $user = User::create([
            'name' => 'محمي', 'email' => 'tfa4@t.test', 'password' => 'strong-password-9',
            'role' => 'student', 'status' => 'active',
        ]);

        $twoFactor = app(TwoFactor::class);
        $secret = $twoFactor->generateFor($user);
        $twoFactor->confirm($user->refresh(), app(Google2FA::class)->getCurrentOtp($secret));

        return $user->refresh();
    });

    tenancy()->initialize($tenant);
    test()->actingAs($user);

    // جلسة مسروقة لا تُنزع الحماية
    tenantDelete($tenant, '/account/two-factor', ['password' => 'wrong'])->assertSessionHasErrors('password');
    $tenant->run(fn () => expect(app(TwoFactor::class)->isEnabled(User::find($user->getKey())))->toBeTrue());

    tenantDelete($tenant, '/account/two-factor', ['password' => 'strong-password-9'])->assertRedirect();
    $tenant->run(fn () => expect(app(TwoFactor::class)->isEnabled(User::find($user->getKey())))->toBeFalse());
});

it('يمنع إطفاء التوثيق حين يكون إلزامياً', function (): void {
    $tenant = provision();

    $user = $tenant->run(function (): User {
        setting()->set('users.two_factor', 'required');

        $user = User::create([
            'name' => 'محمي', 'email' => 'tfa5@t.test', 'password' => 'strong-password-9',
            'role' => 'student', 'status' => 'active',
        ]);

        $twoFactor = app(TwoFactor::class);
        $secret = $twoFactor->generateFor($user);
        $twoFactor->confirm($user->refresh(), app(Google2FA::class)->getCurrentOtp($secret));

        return $user->refresh();
    });

    tenancy()->initialize($tenant);
    test()->actingAs($user);

    tenantDelete($tenant, '/account/two-factor', ['password' => 'strong-password-9'])->assertForbidden();
});

// ------------------------------------------------------------------
// الملف الشخصي
// ------------------------------------------------------------------

it('يفتح الملف الشخصي ويحفظ البيانات ولا يغيّر البريد', function (): void {
    $tenant = provision();

    actingAsOwner($tenant);

    tenantGet($tenant, '/account')->assertOk()->assertSee('بياناتك');

    tenantPut($tenant, '/account', [
        'name' => 'الاسم الجديد', 'locale' => 'en', 'phone' => '01000009999',
        'email_display' => 'hacker@evil.test',
    ])->assertRedirect();

    $tenant->run(function (): void {
        $owner = User::where('role', 'owner')->firstOrFail();

        expect($owner->name)->toBe('الاسم الجديد')
            ->and($owner->locale)->toBe('en')
            ->and($owner->email)->not->toBe('hacker@evil.test');
    });
});

it('يغيّر كلمة المرور بالحالية ويرفضها بلا صحّتها', function (): void {
    $tenant = provision();
    Queue::fake();

    $user = $tenant->run(fn () => User::create([
        'name' => 'مستخدم', 'email' => 'pw@t.test', 'password' => 'current-password-1',
        'role' => 'student', 'status' => 'active',
    ]));

    tenancy()->initialize($tenant);
    test()->actingAs($user);

    tenantPut($tenant, '/account/password', [
        'current_password' => 'wrong-one',
        'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9',
    ])->assertSessionHasErrors('current_password');

    tenantPut($tenant, '/account/password', [
        'current_password' => 'current-password-1',
        'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9',
    ])->assertRedirect();

    $tenant->run(fn () => expect(Hash::check('brand-new-pass-9', User::find($user->getKey())->password))->toBeTrue());
});

it('يُغلق الحساب بيد صاحبه ولا يمحو سجلّه', function (): void {
    $tenant = provision();

    $user = $tenant->run(fn () => User::create([
        'name' => 'راحل', 'email' => 'leaving@t.test', 'password' => 'my-password-9',
        'role' => 'student', 'status' => 'active',
    ]));

    tenancy()->initialize($tenant);
    test()->actingAs($user);

    tenantDelete($tenant, '/account', ['password' => 'my-password-9'])->assertRedirect();

    $tenant->run(function () use ($user): void {
        $row = User::find($user->getKey());

        // حذفٌ ناعم: الفاتورة تُحفظ سنوات بحكم القانون ولا تبقى بلا صاحب
        expect($row)->not->toBeNull()
            ->and($row->status)->toBe('suspended')
            ->and($row->email)->toContain('@deleted.invalid');
    });
});

it('يمنع صاحب المنصّة من حذف حسابه من هنا', function (): void {
    $tenant = provision();

    actingAsOwner($tenant);

    tenantDelete($tenant, '/account', ['password' => 'password'])->assertForbidden();
});
