<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\TwoFactor;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * يفعّل التوثيق بخطوتين لحساب تجريبي — لبوابات المتصفّح في CI.
 *
 * شاشة التحدّي لا تُفتَح إلا بجلسة معلّقة، والجلسة المعلّقة لا تُنشأ
 * إلا بدخول حساب مفعَّل عليه التوثيق. فبغير هذا الأمر تبقى الشاشة
 * خارج الفحص البصري.
 */
final class EnableDemoTwoFactor extends Command
{
    protected $signature = 'demo:two-factor
        {--slug=demo : نطاق المشترك التجريبي}
        {--email=karim@t.test : بريد الحساب}';

    protected $description = 'يفعّل التوثيق بخطوتين لحساب في مشترك تجريبي';

    public function handle(TwoFactor $twoFactor): int
    {
        $tenant = Tenant::where('slug', (string) $this->option('slug'))->first();

        if ($tenant === null) {
            $this->error('لا مشترك بهذا النطاق.');

            return self::FAILURE;
        }

        $email = (string) $this->option('email');

        $done = $tenant->run(function () use ($twoFactor, $email): bool {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                return false;
            }

            $twoFactor->generateFor($user);
            $user->forceFill(['two_factor_confirmed_at' => now()])->save();

            return true;
        });

        if (! $done) {
            $this->error('لا حساب بهذا البريد.');

            return self::FAILURE;
        }

        $this->line($email);

        return self::SUCCESS;
    }
}
