<?php

declare(strict_types=1);

namespace App\Modules\Growth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** إرسالة واحدة — القيد الفريد يمنع تكرار الخطوة نفسها. */
final class CampaignSend extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(CampaignEnrolment::class, 'enrolment_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(CampaignStep::class, 'step_id');
    }
}
