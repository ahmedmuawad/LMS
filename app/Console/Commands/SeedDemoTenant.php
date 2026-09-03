<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenancy\Actions\ProvisionTenant;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * مشترك تجريبي للتطوير وفحوص الواجهة الآلية.
 *   php artisan demo:tenant --mode=center
 */
final class SeedDemoTenant extends Command
{
    protected $signature = 'demo:tenant
        {--slug=demo : النطاق الفرعي}
        {--mode=marketplace : نمط المنصة}
        {--plan=growth : الباقة}
        {--fresh : احذف المشترك القائم بنفس النطاق أولاً}';

    protected $description = 'ينشئ مشتركاً تجريبياً ببيانات واقعية';

    public function handle(ProvisionTenant $provision): int
    {
        $slug = (string) $this->option('slug');

        if ($existing = Tenant::where('slug', $slug)->first()) {
            if (! $this->option('fresh')) {
                $this->warn("المشترك [{$slug}] موجود بالفعل. استخدم --fresh لإعادة إنشائه.");

                return self::SUCCESS;
            }

            $existing->delete();
        }

        $tenant = $provision->handle([
            'name' => 'أكاديمية معوّض',
            'slug' => $slug,
            'owner_email' => 'ahmed@example.test',
            'owner_name' => 'أحمد معوّض',
            'plan_key' => (string) $this->option('plan'),
            'platform_mode' => (string) $this->option('mode'),
            'delivery_mode' => 'blended',
            'password' => 'password',
        ]);

        $tenant->run(function (): void {
            $people = [
                ['سارة عبد الرحمن', 'sara@t.test', 'active', true, '01000000001'],
                ['يوسف حمدي', 'youssef@t.test', 'pending', false, '01000000002'],
                ['منة الله طارق', 'mennah@t.test', 'suspended', false, '01000000003'],
                ['عمر السيد', 'omar@t.test', 'active', true, '01000000004'],
                ['هبة صلاح', 'heba@t.test', 'active', false, '01000000005'],
                ['كريم مصطفى', 'karim@t.test', 'active', true, '01000000006'],
                ['نورهان أشرف', 'nourhan@t.test', 'active', false, '01000000007'],
            ];

            foreach ($people as [$name, $email, $status, $legacy, $phone]) {
                DB::table('users')->insert([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'status' => $status,
                    'legacy_hash' => $legacy,
                    'email_verified_at' => $legacy ? now() : null,
                    'last_seen_at' => now()->subDays(random_int(0, 20)),
                    'created_at' => now()->subDays(random_int(1, 90)),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->info('تم إنشاء المشترك التجريبي.');
        $this->line('  النطاق: '.$tenant->domains->first()?->domain);
        $this->line('  الدخول: ahmed@example.test / password');
        $this->line('  النمط:  '.$tenant->platform_mode.' · الباقة: '.$tenant->plan_key);

        return self::SUCCESS;
    }
}
