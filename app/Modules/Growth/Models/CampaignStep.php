<?php

declare(strict_types=1);

namespace App\Modules\Growth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CampaignStep extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'is_active' => 'boolean'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function delayLabel(): string
    {
        $minutes = (int) $this->delay_minutes;

        return match (true) {
            $minutes < 60 => trans_choice('{1} دقيقة|{2} دقيقتان|[3,10] :count دقائق|[11,*] :count دقيقة', $minutes, ['count' => $minutes]),
            $minutes < 1440 => trans_choice('{1} ساعة|{2} ساعتان|[3,10] :count ساعات|[11,*] :count ساعة', intdiv($minutes, 60), ['count' => intdiv($minutes, 60)]),
            default => trans_choice('{1} يوم|{2} يومان|[3,10] :count أيام|[11,*] :count يوماً', intdiv($minutes, 1440), ['count' => intdiv($minutes, 1440)]),
        };
    }
}
