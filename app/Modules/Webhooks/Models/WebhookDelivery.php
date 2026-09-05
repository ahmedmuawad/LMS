<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجلّ تسليم — لأن «لم يصلني شيء» شكوى بلا دليل.
 *
 * وبلا سجلٍّ لا يُعرف أرسلنا ولم يستقبل، أم لم نرسل أصلاً. وهذا
 * أوّل سؤالٍ في كل تكاملٍ لا يعمل.
 */
final class WebhookDelivery extends Model
{
    /** ما يُحفظ من جواب المستقبِل */
    public const RESPONSE_LIMIT = 500;

    /** @var list<string> */
    protected $fillable = [
        'endpoint_id', 'event', 'payload', 'status_code',
        'attempt', 'response', 'error', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }

    public function succeeded(): bool
    {
        return $this->status_code !== null && $this->status_code >= 200 && $this->status_code < 300;
    }
}
