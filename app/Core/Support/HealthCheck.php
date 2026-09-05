<?php

declare(strict_types=1);

namespace App\Core\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * فحص صحّة المنصة.
 *
 * اكتُشف اليوم أن عامل الطوابير لم يكن مركّباً أصلاً، وأن مجدول
 * المهام بلا كرون — فإيميلات التفعيل تُصفّ ولا تُرسَل، ودورة
 * الفوترة لا تدور. ولم ينبّه إلى ذلك شيء، لأن لا شيء كان يسأل.
 *
 * وكل فحصٍ هنا كُتب لأن غيابه أوقع عطلاً فعلياً — لا لأنه يبدو
 * فحصاً حسناً.
 */
final class HealthCheck
{
    /** المهلة التي بعدها نعدّ المهمة عالقة */
    private const STUCK_MINUTES = 15;

    /**
     * @return list<array{key:string, label:string, ok:bool, detail:string, critical:bool}>
     */
    public function run(): array
    {
        return [
            $this->queueWorker(),
            $this->scheduler(),
            $this->failedJobs(),
            $this->database(),
            $this->cache(),
            $this->storage(),
            $this->backups(),
        ];
    }

    public function isHealthy(): bool
    {
        foreach ($this->run() as $check) {
            if ($check['critical'] && ! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * العامل يُقاس بالمهام العالقة لا بوجود العملية.
     *
     * وجودُ العملية لا يعني أنها تعمل: عاملٌ قائمٌ على اتصال قاعدة
     * ميّت يبدو حيّاً في `ps` ولا ينفّذ شيئاً. أمّا مهمةٌ حان وقتها
     * منذ ربع ساعة ولم تُنفَّذ فدليلٌ قاطع.
     */
    private function queueWorker(): array
    {
        try {
            $stuck = DB::table('jobs')
                ->where('available_at', '<=', now()->subMinutes(self::STUCK_MINUTES)->timestamp)
                ->count();

            return [
                'key' => 'queue',
                'label' => __('عامل الطوابير'),
                'ok' => $stuck === 0,
                'critical' => true,
                'detail' => $stuck === 0
                    ? __('لا مهام عالقة.')
                    : __(':n مهمة حان وقتها ولم تُنفَّذ منذ :m دقيقة — الإيميلات والتذكيرات لا تصل.', [
                        'n' => $stuck, 'm' => self::STUCK_MINUTES,
                    ]),
            ];
        } catch (Throwable $e) {
            return $this->failure('queue', __('عامل الطوابير'), $e);
        }
    }

    /**
     * المجدول يُقاس بأثره لا بجدوله.
     *
     * `schedule:list` يقول ما ينبغي أن يعمل، لا ما عمل. وأصدق أثرٍ
     * نملكه ختمٌ يكتبه المجدول نفسه في كل دورة.
     */
    private function scheduler(): array
    {
        $last = Cache::get('scheduler:last_run');
        $stale = $last === null || now()->parse($last)->addMinutes(20)->isPast();

        return [
            'key' => 'scheduler',
            'label' => __('مجدول المهام'),
            'ok' => ! $stale,
            'critical' => true,
            'detail' => $stale
                ? __('لم يعمل منذ :when — الفوترة والتذكيرات متوقّفة. تأكّد من إعداد الكرون.', [
                    'when' => $last === null ? __('الإطلاق') : now()->parse($last)->diffForHumans(),
                ])
                : __('آخر دورة :when.', ['when' => now()->parse($last)->diffForHumans()]),
        ];
    }

    private function failedJobs(): array
    {
        try {
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

            return [
                'key' => 'failed_jobs',
                'label' => __('المهام الفاشلة'),
                'ok' => $failed === 0,
                'critical' => false,
                'detail' => $failed === 0
                    ? __('لا فشل في آخر ٢٤ ساعة.')
                    : __(':n مهمة فشلت في آخر ٢٤ ساعة.', ['n' => $failed]),
            ];
        } catch (Throwable $e) {
            return $this->failure('failed_jobs', __('المهام الفاشلة'), $e);
        }
    }

    private function database(): array
    {
        try {
            $start = microtime(true);
            DB::select('select 1');
            $ms = (int) ((microtime(true) - $start) * 1000);

            return [
                'key' => 'database',
                'label' => __('القاعدة المركزية'),
                'ok' => $ms < 500,
                'critical' => true,
                'detail' => __('استجابت في :ms مللي ثانية.', ['ms' => $ms]),
            ];
        } catch (Throwable $e) {
            return $this->failure('database', __('القاعدة المركزية'), $e);
        }
    }

    private function cache(): array
    {
        try {
            $key = 'health:'.bin2hex(random_bytes(4));
            Cache::put($key, '1', 10);
            $ok = Cache::get($key) === '1';
            Cache::forget($key);

            return [
                'key' => 'cache',
                'label' => __('الكاش'),
                'ok' => $ok,
                'critical' => false,
                'detail' => $ok ? __('يكتب ويقرأ.') : __('لا يحتفظ بما يُكتب فيه.'),
            ];
        } catch (Throwable $e) {
            return $this->failure('cache', __('الكاش'), $e);
        }
    }

    private function storage(): array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        if ($free === false || $total === false || $total <= 0) {
            return [
                'key' => 'storage', 'label' => __('مساحة القرص'),
                'ok' => true, 'critical' => false, 'detail' => __('غير معروفة.'),
            ];
        }

        $percentFree = round($free / $total * 100, 1);

        return [
            'key' => 'storage',
            'label' => __('مساحة القرص'),
            // عشرة بالمئة: النسخ الاحتياطي وحده قد يأكل أكثر منها
            'ok' => $percentFree > 10,
            'critical' => true,
            'detail' => __('المتاح :gb جيجابايت (:p٪).', [
                'gb' => number_format($free / 1_073_741_824, 1),
                'p' => $percentFree,
            ]),
        ];
    }

    private function backups(): array
    {
        $root = storage_path('backups');
        $days = File::isDirectory($root) ? File::directories($root) : [];

        $latest = collect($days)->map(fn (string $d): string => basename($d))
            ->filter(fn (string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))
            ->sort()->last();

        $stale = $latest === null || now()->parse($latest)->addDays(2)->isPast();

        return [
            'key' => 'backups',
            'label' => __('النسخ الاحتياطي'),
            'ok' => ! $stale,
            'critical' => true,
            'detail' => $latest === null
                ? __('لا توجد نسخة احتياطية إطلاقاً.')
                : __('آخر نسخة :date.', ['date' => $latest]),
        ];
    }

    /** @return array{key:string, label:string, ok:bool, detail:string, critical:bool} */
    private function failure(string $key, string $label, Throwable $e): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'ok' => false,
            'critical' => true,
            'detail' => mb_substr($e->getMessage(), 0, 160),
        ];
    }
}
