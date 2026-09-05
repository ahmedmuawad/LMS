<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * نسخ احتياطي لكل قواعد المنصة — المركزية وقواعد المشتركين.
 *
 * لم يكن هناك نسخٌ احتياطي إطلاقاً. ومنصّةٌ تحمل بيانات مدارس
 * وأقساط طلبة بلا نسخة احتياطية ليست منصّةً بل مقامرة: عطلٌ واحد
 * في القرص يمحو أعمال مشتركين لا نملك تعويضهم عنها.
 *
 * ## لماذا `mysqldump` لا حزمة جاهزة
 *
 * الحزم تُحسن نسخ قاعدةٍ واحدة، وعندنا قاعدةٌ لكل مشترك تُنشأ
 * وتُحذف مع اشتراكه. والقائمة تُقرأ من جدول المشتركين لحظةَ النسخ،
 * فمشتركٌ سجّل أمس يُنسَخ الليلة بلا إعداد.
 */
final class BackupDatabases extends Command
{
    protected $signature = 'backup:run
        {--keep=14 : كم يوماً تُحفظ النسخ}
        {--tenant= : مشترك بعينه بدل الجميع}';

    protected $description = 'ينسخ القاعدة المركزية وقواعد المشتركين احتياطياً';

    public function handle(): int
    {
        $dir = storage_path('backups/'.now()->format('Y-m-d'));
        File::ensureDirectoryExists($dir, 0750);

        $done = 0;
        $failed = 0;

        if (! $this->option('tenant')) {
            $this->dump($this->centralDatabase(), $dir.'/central.sql.gz') ? $done++ : $failed++;
        }

        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->where('slug', $this->option('tenant')))
            ->whereNotNull('provisioned_at')
            ->get();

        foreach ($tenants as $tenant) {
            /*
             | مشترك متعثّر لا يوقف النسخ.
             |
             | قاعدةٌ تالفة أو محذوفة يدوياً تُفشل أمرها، وإيقاف
             | الدورة عندها يحرم بقية المشتركين من نسختهم — وهم
             | أكثر من الواحد المتعثّر دائماً.
             */
            $name = $tenant->database()->getName();

            $this->dump($name, $dir.'/'.$tenant->slug.'.sql.gz') ? $done++ : $failed++;
        }

        $this->prune((int) $this->option('keep'));

        $this->info("نُسخ: {$done} · فشل: {$failed} · المجلد: {$dir}");

        // الفشل يُبلَّغ برمز خروج: الكرون الصامت لا يُنبّه أحداً
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function dump(string $database, string $target): bool
    {
        $connection = config('database.connections.'.config('tenancy.database.central_connection'));

        /*
         | SQLite في التطوير: نسخُ الملفّ هو النسخة الاحتياطية.
         |
         | و`database` قد يكون مساراً مطلقاً (القاعدة المركزية) أو
         | اسم ملفٍّ داخل مجلد القواعد (قواعد المشتركين) — فيُجرَّب
         | كما هو أولاً ثم يُبنى المسار.
         */
        if (($connection['driver'] ?? '') === 'sqlite') {
            $file = File::exists($database) ? $database : database_path($database);

            if (! File::exists($file)) {
                $this->error("[{$database}] الملف غير موجود.");

                return false;
            }

            return File::copy($file, str_replace('.sql.gz', '.sqlite', $target));
        }

        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --quick --no-tablespaces %s | gzip > %s',
            escapeshellarg((string) ($connection['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($connection['port'] ?? 3306)),
            escapeshellarg((string) ($connection['username'] ?? '')),
            escapeshellarg((string) ($connection['password'] ?? '')),
            escapeshellarg($database),
            escapeshellarg($target),
        );

        try {
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(1800);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->error("[{$database}] ".mb_substr($process->getErrorOutput(), 0, 200));

                return false;
            }

            // ملفٌّ فارغ ليس نسخة: النجاح الصامت أخطر من الفشل الصريح
            return filesize($target) > 100;
        } catch (Throwable $e) {
            $this->error("[{$database}] ".mb_substr($e->getMessage(), 0, 200));

            return false;
        }
    }

    /** الاحتفاظ محدود: قرصٌ يمتلئ بالنسخ يُوقف المنصّة التي جاءت النسخ لحمايتها */
    private function prune(int $keepDays): void
    {
        $root = storage_path('backups');

        foreach (File::directories($root) as $folder) {
            $day = basename($folder);

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                continue;
            }

            if (now()->parse($day)->addDays($keepDays)->isPast()) {
                File::deleteDirectory($folder);
            }
        }
    }

    private function centralDatabase(): string
    {
        return (string) config('database.connections.'.config('tenancy.database.central_connection').'.database');
    }
}
