<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Models;

use App\Core\Entitlements\Entitlements;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * ADR-009 — المشترك: عميلنا في طبقة الـ SaaS.
 * بياناته التشغيلية (طلاب، كورسات، طلبات) في قاعدة مستقلة تماماً.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string $platform_mode
 * @property string $delivery_mode
 * @property bool $center_enabled
 * @property string $country
 * @property string $currency
 * @property string|null $plan_key
 */
final class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    private ?Entitlements $entitlements = null;

    /** الأعمدة الحقيقية — ما عداها يذهب إلى عمود data كـ JSON. */
    public static function getCustomColumns(): array
    {
        return [
            'id', 'name', 'slug', 'owner_name', 'owner_email', 'owner_phone',
            'platform_mode', 'delivery_mode', 'center_enabled', 'theme',
            'country', 'currency', 'locale', 'timezone',
            'status', 'plan_key', 'trial_ends_at', 'suspended_at', 'archived_at',
            'db_shard', 'provision_error', 'provisioned_at',

            // بوّابة واتساب: نسخة المشترك ومفتاحها ورقمه المرتبط
            'wa_instance', 'wa_token', 'wa_number',
        ];
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'center_enabled' => 'boolean',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'archived_at' => 'datetime',
            'provisioned_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------
    // الصلاحيات (ADR-011)
    // ---------------------------------------------------------------

    public function entitlements(): Entitlements
    {
        return $this->entitlements ??= new Entitlements($this->id, $this->plan_key);
    }

    public function allows(string $feature): bool
    {
        return $this->entitlements()->allows($feature);
    }

    public function limitOf(string $feature): ?int
    {
        return $this->entitlements()->limit($feature);
    }

    public function usageOf(string $feature): int
    {
        return $this->entitlements()->usage($feature);
    }

    public function remainingOf(string $feature): ?int
    {
        return $this->entitlements()->remaining($feature);
    }

    public function hasReachedLimit(string $feature): bool
    {
        return $this->entitlements()->hasReachedLimit($feature);
    }

    /**
     * يُفرغ الصلاحيات المحفوظة ويُسقط النسخة المحمّلة.
     * لازم بعد تغيير الباقة: مفتاح الحفظ يحمل اسمها.
     */
    public function forgetEntitlements(): void
    {
        $this->entitlements()->flush();
        $this->entitlements = null;
    }

    // ---------------------------------------------------------------
    // الأنماط (ADR-010)
    // ---------------------------------------------------------------

    public function isMode(string ...$modes): bool
    {
        return in_array($this->platform_mode, $modes, true);
    }

    public function hasMultipleInstructors(): bool
    {
        return $this->isMode('marketplace', 'hybrid');
    }

    public function managesCenter(): bool
    {
        // المدرّس المستقل يدير حصصه ومجموعاته وإن لم يملك سنتراً
        return $this->center_enabled || $this->isMode('teacher', 'center', 'hybrid');
    }

    public function offersLive(): bool
    {
        return in_array($this->delivery_mode, ['live', 'blended'], true);
    }

    // ---------------------------------------------------------------
    // دورة الحياة
    // ---------------------------------------------------------------

    /** المنصة تعمل للطلاب — لا نعاقب الطالب بمشكلة اشتراك مشتركه. */
    public function isPubliclyAvailable(): bool
    {
        return ! in_array($this->status, ['provisioning', 'archived'], true);
    }

    /** لوحة تحكم المشترك مفتوحة له. */
    public function canAccessDashboard(): bool
    {
        return in_array($this->status, ['trialing', 'active', 'past_due'], true);
    }

    public function onTrial(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function trialDaysLeft(): int
    {
        return $this->onTrial() ? (int) ceil(now()->floatDiffInDays($this->trial_ends_at)) : 0;
    }
}
