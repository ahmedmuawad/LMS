<?php

declare(strict_types=1);

namespace App\Modules\Live;

use Illuminate\Support\Carbon;

/**
 * غرفة حصة جاهزة: رابطها، ومتى تُفتح، وهل هي مفتوحة الآن.
 *
 * كائنٌ واحد يمرّ من المزوّد إلى الشاشة، فلا تحسب كل شاشة نافذة
 * الفتح بنفسها — وحسابان للنافذة يفترقان يوماً ما، فيرى الطالب
 * زرّاً يعمل والمدرّس زرّاً لا يعمل.
 */
final class LiveMeeting
{
    public function __construct(
        public readonly string $provider,
        public readonly string $url,
        public readonly ?string $room = null,
        public readonly ?Carbon $opensAt = null,
        public readonly ?Carbon $closesAt = null,
    ) {}

    public function isOpen(): bool
    {
        if ($this->opensAt === null) {
            return true;
        }

        return now()->between($this->opensAt, $this->closesAt ?? $this->opensAt->copy()->addHours(4));
    }

    /** متى يُفتح — بصيغة يقرؤها الطالب، لا طابعاً زمنياً */
    public function opensInLabel(): ?string
    {
        if ($this->opensAt === null || $this->isOpen()) {
            return null;
        }

        return $this->opensAt->isPast()
            ? __('انتهت الحصة')
            : __('يُفتح :when', ['when' => $this->opensAt->diffForHumans()]);
    }
}
