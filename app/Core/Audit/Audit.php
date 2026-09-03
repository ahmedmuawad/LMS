<?php

declare(strict_types=1);

namespace App\Core\Audit;

use App\Core\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * نقطة واحدة لتقييد أفعالنا، حتى لا يعتمد التقييد على تذكّر كل متحكّم.
 */
final class Audit
{
    public static function record(
        string $action,
        ?string $tenantId = null,
        ?Model $subject = null,
        array $meta = [],
    ): AuditLog {
        $actor = Auth::guard('super')->user();

        return AuditLog::create([
            'actor_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->name ?? __('النظام'),
            'action' => $action,
            'tenant_id' => $tenantId,
            'subject_type' => $subject === null ? null : $subject::class,
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
            'meta' => $meta === [] ? null : $meta,
            'ip' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 512),
            'created_at' => now(),
        ]);
    }
}
