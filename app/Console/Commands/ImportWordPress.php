<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Migration\WordPressImporter;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * استيراد مدرسة من ووردبريس إلى مشترك.
 *
 * وهو سبب المشروع: مدرسةٌ قائمة بمئات الطلبة وسنوات من المحتوى لا
 * تنتقل إلى منصّةٍ تطلب منها أن تبدأ من الصفر.
 *
 * ويعرض ما سيقع قبل أن يقع: الاستيراد يكتب في قاعدةٍ عليها بيانات،
 * و`--force` وحدها هي التي تُنفّذ.
 */
final class ImportWordPress extends Command
{
    protected $signature = 'wp:import
        {tenant : نطاق المشترك الفرعي}
        {--host=127.0.0.1 : خادم قاعدة ووردبريس}
        {--port=3306}
        {--database= : اسم القاعدة}
        {--username=}
        {--password=}
        {--prefix=wp_ : بادئة الجداول}
        {--force : نفّذ فعلاً — بدونها يُعرض ما سيقع فقط}';

    protected $description = 'يستورد كورسات ووردبريس/WPLMS وطلبتها وتسجيلاتهم';

    public function handle(WordPressImporter $importer): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->first();

        if ($tenant === null) {
            $this->error("لا مشترك بالنطاق [{$this->argument('tenant')}].");

            return self::FAILURE;
        }

        if (blank($this->option('database'))) {
            $this->error('حدّد اسم قاعدة ووردبريس بـ --database.');

            return self::FAILURE;
        }

        try {
            $importer->connect([
                'host' => (string) $this->option('host'),
                'port' => (int) $this->option('port'),
                'database' => (string) $this->option('database'),
                'username' => (string) $this->option('username'),
                'password' => (string) $this->option('password'),
                'prefix' => (string) $this->option('prefix'),
            ]);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return $tenant->run(function () use ($importer): int {
            $preview = $importer->preview();

            $this->line('');
            $this->info('ما وجدناه في ووردبريس:');

            foreach ($preview as $key => $count) {
                $this->line(sprintf('  %-12s %d', $this->label($key), $count));
            }

            if (! $this->option('force')) {
                $this->line('');
                $this->warn('هذا عرضٌ فقط — أضف --force للتنفيذ.');

                return self::SUCCESS;
            }

            $this->line('');
            $this->info('يُستورَد…');

            $result = $importer->run();

            $this->line('');
            $this->info('تمّ:');

            foreach ($result as $key => $count) {
                $this->line(sprintf('  %-12s %d', $this->label($key), $count));
            }

            $this->line('');
            $this->comment('كلمات المرور نُقلت كما هي: يدخل كل طالب بكلمته القديمة، وتُرقَّى إلى معيارنا عند أول دخول.');

            return self::SUCCESS;
        });
    }

    private function label(string $key): string
    {
        return match ($key) {
            'courses' => 'كورسات',
            'lessons' => 'دروس',
            'quizzes' => 'اختبارات',
            'users' => 'مستخدمون',
            'enrollments' => 'تسجيلات',
            'posts' => 'مقالات',
            'skipped' => 'متخطّى',
            default => $key,
        };
    }
}
