<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Services\Models\Booking;
use Illuminate\Console\Command;

/**
 * رمز أول حجز في مشترك تجريبي — لبوابات المتصفّح في CI.
 *
 * رابط الحجز يحمل رمزاً عشوائياً لا رقماً متسلسلاً، فلا يمكن
 * تركيبه في ملف الـCI كما كان يُركَّب رقم الطلب.
 */
final class ShowDemoBookingToken extends Command
{
    protected $signature = 'demo:booking-token {--slug=demo : نطاق المشترك التجريبي}';

    protected $description = 'يطبع رمز أول حجز في مشترك تجريبي';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', (string) $this->option('slug'))->first();

        if ($tenant === null) {
            $this->error('لا مشترك بهذا النطاق.');

            return self::FAILURE;
        }

        $token = $tenant->run(fn (): ?string => Booking::orderBy('id')->value('token'));

        if ($token === null) {
            $this->error('لا حجوزات في هذا المشترك.');

            return self::FAILURE;
        }

        $this->line($token);

        return self::SUCCESS;
    }
}
