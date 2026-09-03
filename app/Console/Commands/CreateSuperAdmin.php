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
        {--role=super_admin : الدور (super_admin|support|finance)}';

    protected $description = 'ينشئ حساباً لفريق المنصة على القاعدة المركزية';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('البريد الإلكتروني');

        if (SuperAdmin::where('email', $email)->exists()) {
            $this->error("الحساب [{$email}] موجود بالفعل.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('كلمة المرور');

        if (mb_strlen((string) $password) < 8) {
            $this->error('كلمة المرور يجب ألا تقل عن ٨ أحرف.');

            return self::FAILURE;
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
