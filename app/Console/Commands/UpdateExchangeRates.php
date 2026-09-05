<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Support\ExchangeRates;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * تحديث أسعار الصرف — يومياً بالجدول.
 *
 * وسعرٌ يُكتب مرّةً ويبقى سنةً يجعل كورساً بالريال يُباع بسعر العام
 * الماضي، والفرق يخرج من جيب المشترك بلا أن يدري.
 */
final class UpdateExchangeRates extends Command
{
    protected $signature = 'rates:update';

    protected $description = 'يُحدّث أسعار صرف العملات من مصدرها';

    public function handle(ExchangeRates $rates): int
    {
        try {
            $result = $rates->refresh();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(__('حُدّثت :count عملة، الأساس :base.', [
            'count' => $result['updated'],
            'base' => $result['base'],
        ]));

        return self::SUCCESS;
    }
}
