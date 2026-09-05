<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Access\Ability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * نسخ بيانات المشترك الاحتياطية — يراها وينزّلها.
 *
 * ## النسخ كان يعمل بلا أن يراه أحد
 *
 * `backup:run` يعمل ليلاً منذ أن كُتب، ويكتب في `storage/backups`.
 * ولا يعرف المشترك أنه موجود، ولا متى كانت آخر نسخة، ولا يستطيع
 * أخذ نسخته قبل تعديلٍ كبير. فيسألنا — أو الأسوأ: لا يسأل، ويظنّ
 * أن لا نسخَ عندنا.
 *
 * ## ولا يرى إلا نسخته هو
 *
 * الملفّات كلّها في مجلّدٍ واحد باسم كل مشترك؛ ومسارٌ يأتي من الرابط
 * بلا حراسةٍ ينزّل قاعدة الجار. فالاسم يُبنى هنا من هويّة المشترك
 * ولا يُقرأ من الطلب أبداً.
 *
 * ## والطلب مُحدَّد بمرّةٍ في الساعة
 *
 * `mysqldump` يقرأ القاعدة كاملة؛ وزرٌّ بلا حدٍّ يجعل ضغطاً متكرّراً
 * يُثقل الخادم على كل المشتركين.
 */
final class BackupController
{
    /** كم يُنتظَر بين طلبٍ وآخر */
    private const COOLDOWN_MINUTES = 60;

    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('tenant.backups', [
            'backups' => $this->mine(),
            'availableIn' => RateLimiter::availableIn($this->limitKey()),
            'cooldown' => self::COOLDOWN_MINUTES,
        ]);
    }

    /** يطلب نسخةً الآن — قبل تعديلٍ كبير مثلاً */
    public function store(Request $request): RedirectResponse
    {
        $this->authorise($request);

        if (RateLimiter::tooManyAttempts($this->limitKey(), 1)) {
            return back()->withErrors(['backup' => __('طلبتَ نسخةً قريباً. حاول بعد :n دقيقة.', [
                'n' => (int) ceil(RateLimiter::availableIn($this->limitKey()) / 60),
            ])]);
        }

        RateLimiter::hit($this->limitKey(), self::COOLDOWN_MINUTES * 60);

        /*
         | الأمر يُنفَّذ لمشترك واحد لا للجميع.
         |
         | ونسخُ كل المشتركين لأن أحدهم ضغط زرّاً يجعل ضغطةً واحدة
         | تُثقل الخادم على الكلّ.
         */
        Artisan::queue('backup:run', ['--tenant' => (string) tenant('slug')]);

        return back()->with('status', __('طُلبت النسخة — تظهر هنا خلال دقائق.'));
    }

    /** ينزّل نسخةً بعينها */
    public function download(Request $request, string $date): BinaryFileResponse
    {
        $this->authorise($request);

        /*
         | التاريخ وحده يأتي من الرابط، وبصيغةٍ محصورة.
         |
         | واسمُ الملفّ يُبنى من هويّة المشترك لا من الطلب — وإلا
         | نزّل مشتركٌ قاعدة جاره بتبديل حرفٍ في الرابط.
         */
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, 404);

        $file = $this->pathFor($date);

        abort_if($file === null, 404);

        return response()->download($file, basename($file));
    }

    /**
     * نسخُ هذا المشترك وحده، الأحدث أولاً.
     *
     * @return list<array{date:string, size:string, at:string}>
     */
    private function mine(): array
    {
        $root = storage_path('backups');

        if (! File::isDirectory($root)) {
            return [];
        }

        $rows = [];

        foreach (File::directories($root) as $dir) {
            $date = basename($dir);
            $file = $this->pathFor($date);

            if ($file === null) {
                continue;
            }

            $rows[] = [
                'date' => $date,
                'size' => $this->humanSize((int) File::size($file)),
                'at' => date('H:i', (int) File::lastModified($file)),
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return $rows;
    }

    /** مسار نسخة هذا المشترك في ذلك اليوم — أو null */
    private function pathFor(string $date): ?string
    {
        $slug = (string) tenant('slug');

        if ($slug === '') {
            return null;
        }

        // MySQL يكتب `.sql.gz`، وSQLite في التطوير يكتب `.sqlite`
        foreach (['.sql.gz', '.sqlite'] as $extension) {
            $candidate = storage_path('backups/'.$date.'/'.$slug.$extension);

            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function humanSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1073741824 => number_format($bytes / 1073741824, 1).' GB',
            $bytes >= 1048576 => number_format($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0).' KB',
            default => $bytes.' B',
        };
    }

    private function limitKey(): string
    {
        return 'backup:'.(tenant('id') ?? 'central');
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::SETTINGS_MANAGE), 403);
    }
}
