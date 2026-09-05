<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * وجهةٌ تستقبل أحداث المنصّة.
 *
 * ## السرّ يُولَّد ولا يُطلب
 *
 * المستقبِل يتحقّق بترويسة `X-Signature` أن الطلب منّا — وبلا سرٍّ
 * قويّ يستطيع أيٌّ كان أن يرسل «تمّ الدفع» إلى نظام المشترك. ومن
 * يُطلب منه اختراع سرٍّ يكتب اسمه أو `123456`.
 */
final class WebhookEndpoint extends Model
{
    /** بعد هذا العدد من الإخفاقات المتتالية تُوقَف الوجهة */
    public const FAILURE_LIMIT = 15;

    /** @var list<string> */
    protected $fillable = ['name', 'url', 'events', 'secret', 'is_active', 'source', 'token_id'];

    /** @var list<string> */
    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_delivered_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $endpoint): void {
            $endpoint->secret ??= self::newSecret();
        });
    }

    public static function newSecret(): string
    {
        return 'whsec_'.Str::random(48);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'endpoint_id');
    }

    /** هل تستقبل هذا الحدث الآن؟ */
    public function wants(string $event): bool
    {
        if (! $this->is_active || $this->disabled_at !== null) {
            return false;
        }

        $events = (array) ($this->events ?? []);

        // `*` تعني كل الأحداث — وهي ما يشترك به Zapier غالباً
        return in_array('*', $events, true) || in_array($event, $events, true);
    }

    /** التوقيع: HMAC-SHA256 على الطابع الزمني والجسم معاً */
    public function sign(string $body, int $timestamp): string
    {
        /*
         | الطابع داخل التوقيع لا خارجه.
         |
         | توقيعُ الجسم وحده يُعاد استعماله إلى الأبد: من التقط
         | طلب «تمّ الدفع» أعاد إرساله ألف مرّة بتوقيعٍ صحيح.
         */
        return hash_hmac('sha256', $timestamp.'.'.$body, (string) $this->secret);
    }
}
