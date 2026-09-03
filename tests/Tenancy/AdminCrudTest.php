<?php

declare(strict_types=1);

use App\Core\Admin\Fields\NumberField;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Hash;

// ADR-004 — نفس التعريف يولّد شاشات الإنشاء والتعديل وقواعد التحقق.

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(CountrySeeder::class);
    $this->seed(FeatureSeeder::class);
    $this->seed(PlanSeeder::class);

    $this->tenant = provision(['name' => 'أكاديمية النماذج', 'owner_email' => 'crud@example.test']);
    $this->base = 'http://'.$this->tenant->domains->first()->domain;
    actingAsOwner($this->tenant);
});

afterEach(fn () => tenancy()->initialized && tenancy()->end());

it('renders the create form from the field definition', function () {
    $this->get($this->base.'/admin/users/create')
        ->assertOk()
        ->assertSee('بيانات الحساب', false)
        ->assertSee('الحالة والتفضيلات', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="phone"', false)
        ->assertSee('حساب مُرحَّل من ووردبريس', false);
});

it('creates a record', function () {
    $this->post($this->base.'/admin/users', [
        'name' => 'طالب جديد',
        'email' => 'new@student.test',
        'phone' => '+201000009999',
        'password' => 'secret-password',
        'status' => 'active',
        'locale' => 'ar',
        'legacy_hash' => '0',
    ])->assertRedirect();

    $this->tenant->run(function (): void {
        $user = User::where('email', 'new@student.test')->first();

        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('طالب جديد')
            ->and($user->status)->toBe('active')
            ->and($user->legacy_hash)->toBeFalse()
            ->and(Hash::check('secret-password', $user->password))->toBeTrue();
    });
});

it('derives validation rules from the fields', function () {
    $this->post($this->base.'/admin/users', ['name' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['name', 'email', 'password', 'status']);
});

it('enforces uniqueness on create but not against the record itself on edit', function () {
    $id = $this->tenant->run(fn () => User::where('email', 'crud@example.test')->value('id'));

    // بريد مستخدَم بالفعل
    $this->post($this->base.'/admin/users', [
        'name' => 'مكرر', 'email' => 'crud@example.test', 'password' => 'secret-password', 'status' => 'active',
    ])->assertSessionHasErrors('email');

    // نفس البريد على نفس السجل عند التعديل: مقبول
    $this->put($this->base.'/admin/users/'.$id, [
        'name' => 'مالك مُعدَّل', 'email' => 'crud@example.test', 'status' => 'active', 'locale' => 'ar',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->tenant->run(fn () => expect(User::find($id)->name)->toBe('مالك مُعدَّل'));
});

it('rejects a status outside the declared options', function () {
    $this->post($this->base.'/admin/users', [
        'name' => 'اختبار', 'email' => 'x@t.test', 'password' => 'secret-password', 'status' => 'god-mode',
    ])->assertSessionHasErrors('status');
});

it('ignores fields that are not part of the form', function () {
    $this->post($this->base.'/admin/users', [
        'name' => 'محاولة', 'email' => 'inject@t.test', 'password' => 'secret-password', 'status' => 'active',
        'wp_user_id' => 999, 'meta' => ['role' => 'root'],   // ليست حقولاً معرّفة
    ])->assertRedirect();

    $this->tenant->run(function (): void {
        $user = User::where('email', 'inject@t.test')->first();
        expect($user->wp_user_id)->toBeNull();
    });
});

it('never sends a hashed password back to the browser', function () {
    $id = $this->tenant->run(fn () => User::first()->id);

    $hash = $this->tenant->run(fn () => User::find($id)->password);

    $this->get($this->base.'/admin/users/'.$id.'/edit')
        ->assertOk()
        ->assertDontSee($hash, false)
        ->assertSee('name="password"', false);
});

it('keeps the old password when the field is left empty on edit', function () {
    $id = $this->tenant->run(fn () => User::first()->id);
    $before = $this->tenant->run(fn () => User::find($id)->password);

    $this->put($this->base.'/admin/users/'.$id, [
        'name' => 'بلا تغيير كلمة مرور', 'email' => 'crud@example.test', 'status' => 'active', 'locale' => 'ar',
    ])->assertRedirect();

    $this->tenant->run(fn () => expect(User::find($id)->password)->toBe($before));
});

it('deletes a record', function () {
    $id = $this->tenant->run(fn () => User::create([
        'name' => 'للحذف', 'email' => 'delete@t.test', 'password' => 'secret-password', 'status' => 'active',
    ])->id);

    $this->delete($this->base.'/admin/users/'.$id)->assertRedirect();

    $this->tenant->run(fn () => expect(User::find($id))->toBeNull());
});

it('returns 404 for a record that does not exist', function () {
    $this->get($this->base.'/admin/users/999999/edit')->assertNotFound();
});

it('converts a money field between decimal and minor units', function () {
    $field = NumberField::make('price')->money('EGP');

    expect($field->fill('750.50'))->toBe(75050)
        ->and($field->fill(''))->toBeNull();
});

it('counts the actual number of invalid fields, not the number of error bags', function () {
    $this->from($this->base.'/admin/users/create')
        ->post($this->base.'/admin/users', [])
        ->assertRedirect();

    $this->from($this->base.'/admin/users/create')
        ->followingRedirects()
        ->post($this->base.'/admin/users', [])
        ->assertSee('حقول تحتاج تصحيحاً', false)
        ->assertDontSee('حقل واحد يحتاج تصحيحاً', false);
});

it('keeps what the user typed after a failed submission', function () {
    // بدون from() يعود التحقق إلى الجذر لا إلى النموذج
    $html = $this->from($this->base.'/admin/users/create')
        ->followingRedirects()
        ->post($this->base.'/admin/users', ['name' => 'اسم مكتوب', 'email' => 'bad'])
        ->assertOk()
        ->getContent();

    // القيم القديمة تعود إلى حقولها، ورسالة الخطأ بالعربية
    expect($html)->toContain('اسم مكتوب')
        ->and($html)->toContain('صيغة البريد الإلكتروني غير صحيحة')
        // نفس الوسم يحمل الحقل وقيمته القديمة (ترتيب السمات غير مضمون)
        ->and(preg_match('/<input[^>]*name="name"[^>]*>/u', $html, $m))->toBe(1)
        ->and($m[0])->toContain('اسم مكتوب');
});
