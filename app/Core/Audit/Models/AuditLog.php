<?php

declare(strict_types=1);

namespace App\Core\Audit\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $action
 * @property array|null $meta
 */
final class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** أسماء الأفعال بالعربية — تُعرض كما هي في اللوحة. */
    public const ACTIONS = [
        'tenant.status_changed' => 'تغيير حالة مشترك',
        'tenant.plan_changed' => 'تغيير باقة مشترك',
        'tenant.feature_overridden' => 'تجاوز ميزة لمشترك',
        'tenant.impersonated' => 'دخول كمشترك',
        'plan.updated' => 'تعديل باقة',
        'subscription.started' => 'بدء اشتراك',
        'subscription.cancelled' => 'إلغاء اشتراك',
        'invoice.paid' => 'سداد فاتورة',
    ];

    public function getConnectionName(): ?string
    {
        // السجل ملكنا نحن، ويبقى على القاعدة المركزية مهما كان السياق
        return config('tenancy.database.central_connection');
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'actor_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function actionLabel(): string
    {
        return __(self::ACTIONS[$this->action] ?? $this->action);
    }
}
