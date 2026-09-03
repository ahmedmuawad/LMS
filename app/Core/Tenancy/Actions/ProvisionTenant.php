<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Actions;

use App\Core\Entitlements\Models\Plan;
use App\Core\Tenancy\Models\Domain;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * ADR-009 — التجهيز الآلي: من الدفع إلى منصة عاملة.
 *
 * كل خطوة قابلة للتراجع؛ الفشل في أي مرحلة يترك الحالة نظيفة
 * ورسالة سبب مفهومة في provision_error بدل مشترك نصف مجهّز.
 */
final class ProvisionTenant
{
    public function __construct(
        private readonly ApplyPlatformMode $applyMode,
    ) {}

    /**
     * @param  array{name:string, owner_email:string, owner_name?:string, owner_phone?:string,
     *               slug?:string, plan_key?:string, platform_mode?:string, delivery_mode?:string,
     *               center_enabled?:bool, country?:string, currency?:string, locale?:string,
     *               timezone?:string, password?:string}  $input
     */
    public function handle(array $input): Tenant
    {
        $slug = $this->uniqueSlug($input['slug'] ?? $input['name']);
        $plan = isset($input['plan_key']) ? Plan::find($input['plan_key']) : null;

        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => $input['name'],
            'slug' => $slug,
            'owner_name' => $input['owner_name'] ?? null,
            'owner_email' => $input['owner_email'],
            'owner_phone' => $input['owner_phone'] ?? null,
            'platform_mode' => $input['platform_mode'] ?? config('platform-modes.default'),
            'delivery_mode' => $input['delivery_mode'] ?? 'recorded',
            'center_enabled' => $input['center_enabled'] ?? false,
            'country' => $input['country'] ?? 'EG',
            'currency' => $input['currency'] ?? $this->currencyFor($input['country'] ?? 'EG'),
            'locale' => $input['locale'] ?? 'ar',
            'timezone' => $input['timezone'] ?? $this->timezoneFor($input['country'] ?? 'EG'),
            'plan_key' => $plan?->key,
            'status' => 'provisioning',
            'trial_ends_at' => $plan ? now()->addDays($plan->trial_days) : null,
        ]);

        try {
            // 1) النطاق الفرعي — دائماً، لكل الباقات
            Domain::create([
                'tenant_id' => $tenant->id,
                'domain' => $slug.'.'.config('tenancy.base_domain', 'localhost'),
                'type' => 'subdomain',
                'is_primary' => true,
                'ssl_status' => 'not_required',
                'verified_at' => now(),
            ]);

            // 2) قاعدة البيانات والهجرات (يتكفّل بها stancl عبر أحداث الإنشاء)
            //    ثم 3) النمط: الموديولات والإعدادات والثيم
            $this->applyMode->handle($tenant);

            // 4) حساب المدير الأول
            $password = $input['password'] ?? Str::password(12);

            $tenant->run(function () use ($input, $password): void {
                DB::table('users')->insert([
                    'name' => $input['owner_name'] ?? $input['name'],
                    'email' => $input['owner_email'],
                    'phone' => $input['owner_phone'] ?? null,
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'locale' => $input['locale'] ?? 'ar',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            $tenant->forceFill([
                'status' => $plan && $plan->trial_days > 0 ? 'trialing' : 'active',
                'provisioned_at' => now(),
                'provision_error' => null,
            ])->save();

            return $tenant->refresh();
        } catch (Throwable $e) {
            Log::error('فشل تجهيز مشترك', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);

            $tenant->forceFill([
                'status' => 'provisioning',
                'provision_error' => Str::limit($e->getMessage(), 250),
            ])->save();

            throw $e;
        }
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'academy';
        $slug = $base;

        for ($i = 2; Tenant::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private function currencyFor(string $country): string
    {
        return DB::table('countries')->where('code', $country)->value('currency') ?? 'USD';
    }

    private function timezoneFor(string $country): string
    {
        return DB::table('countries')->where('code', $country)->value('timezone_default') ?? 'UTC';
    }
}
