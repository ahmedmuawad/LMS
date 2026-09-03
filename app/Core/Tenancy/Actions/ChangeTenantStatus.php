<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Actions;

use App\Core\Audit\Audit;
use App\Core\Tenancy\Models\Tenant;
use InvalidArgumentException;

/**
 * دورة حياة الاشتراك.
 *
 * قاعدة ثابتة: التعليق يقفل لوحة المشترك، ولا يمحو بياناته أبداً.
 * الأرشفة وحدها تُخفي الموقع عن الطلاب، ولا نصل إليها إلا بطلبه.
 */
final class ChangeTenantStatus
{
    /** الانتقالات المسموحة — ما عداها خطأ برمجي لا خطأ مستخدم. */
    public const TRANSITIONS = [
        'provisioning' => ['trialing', 'active', 'cancelled'],
        'trialing' => ['active', 'past_due', 'suspended', 'cancelled'],
        'active' => ['past_due', 'suspended', 'cancelled'],
        'past_due' => ['active', 'suspended', 'cancelled'],
        'suspended' => ['active', 'past_due', 'cancelled', 'archived'],
        'cancelled' => ['active', 'archived'],
        'archived' => ['active'],
    ];

    public function handle(Tenant $tenant, string $status, ?string $reason = null): Tenant
    {
        $from = $tenant->status;

        if ($from === $status) {
            return $tenant;
        }

        if (! in_array($status, self::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidArgumentException("لا يمكن الانتقال من [{$from}] إلى [{$status}].");
        }

        $tenant->status = $status;
        $tenant->suspended_at = $status === 'suspended' ? now() : null;
        $tenant->archived_at = $status === 'archived' ? now() : null;

        if ($status === 'active') {
            $tenant->trial_ends_at = null;
        }

        $tenant->save();

        Audit::record('tenant.status_changed', $tenant->id, $tenant, [
            'from' => $from,
            'to' => $status,
            'reason' => $reason,
        ]);

        return $tenant;
    }

    /** @return array<string, string> الحالات التي يمكن الانتقال إليها الآن */
    public static function allowedFrom(string $status, array $labels): array
    {
        return collect(self::TRANSITIONS[$status] ?? [])
            ->mapWithKeys(fn (string $to): array => [$to => __($labels[$to] ?? $to)])
            ->all();
    }
}
