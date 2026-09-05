<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * نتيجة فحص الجهاز — مسموحٌ أم لا، وكم استُهلك من الحدّ.
 */
final class DeviceCheck
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $used,
        public readonly ?int $limit,
        public readonly string $fingerprint,
    ) {}

    /** الرسالة تقول الرقم والمخرج: «امنع» وحدها تُنتج مكالمة دعم */
    public function message(): string
    {
        return __('بلغتَ حدّ الأجهزة المسموح بها (:used من :limit). افتح «الأمان والدخول» من حسابك وافصل جهازاً لم تعد تستعمله.', [
            'used' => $this->used,
            'limit' => $this->limit,
        ]);
    }
}
