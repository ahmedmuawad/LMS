<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

final class CreateSuperAdmin extends Command
{
    protected $signature = 'super-admin:create
        {--name= : الاسم}
        {--email= : البريد}
        {--password= : كلمة المرور}
        {--role=super_admin : الدور (super_admin|support|finance)}
        {--reset : يعيد تعيين كلمة مرور حساب موجود بدل الرفض}';

    protected $description = 'ينشئ حساباً لفريق المنصة على القاعدة المركزية، أو يعيد تعيين كلمة مروره';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('البريد الإلكتروني');
        $existing = SuperAdmin::where('email', $email)->first();

        if ($existing !== null && ! $this->option('reset')) {
            $this->error("الحساب [{$email}] موجود بالفعل. استخدم --reset لإعادة تعيين كلمة مروره.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('كلمة المرور');

        if (mb_strlen((string) $password) < 8) {
            $this->error('كلمة المرور يجب ألا تقل عن ٨ أحرف.');

            return self::FAILURE;
        }

        if ($existing !== null) {
            // إعادة التفعيل مقصودة: حسابٌ معطّل بكلمة مرور جديدة ما زال بلا دخول،
            // فيبدو الأمر وكأنه فشل صامت.
            $existing->forceFill([
                'password' => Hash::make($password),
                'is_active' => true,
            ])->save();

            $this->info("تم تعيين كلمة مرور جديدة وتفعيل الحساب: {$email}");

            return self::SUCCESS;
        }

        SuperAdmin::create([
            'name' => $this->option('name') ?: $this->ask('الاسم'),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $this->option('role'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->info("تم إنشاء حساب فريق المنصة: {$email}");

        return self::SUCCESS;
    }
}
