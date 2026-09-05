<?php

declare(strict_types=1);

namespace App\Core\Entitlements;

use App\Core\Entitlements\Exceptions\QuotaExceededException;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إنفاذ حدود الباقة.
 *
 * الحدود كانت مكتوبةً في القاعدة ولا يقرأها شيء: مشترك «البداية»
 * بحدّ مئة طالب يُنشئ ألفاً. وهذه الطبقة تجعلها تعمل.
 *
 * ## لماذا العدّ الحيّ لا العدّاد
 *
 * `Entitlements::recordUsage()` تبني عدّاداً تراكمياً، وهو صحيح لما
 * لا يُعَدّ — كالإيميلات المُرسَلة ودقائق الفيديو المرفوعة. أمّا ما
 * يُعَدّ فالعدّاد فيه يَنحرف: يُحذف طالب فيبقى محسوباً إلى الأبد،
 * أو تُستورد دفعة بلا مرور بالعدّاد فلا تُحسب أصلاً. والمشترك الذي
 * منعناه ظلماً يتصل غاضباً، والذي سمحنا له خطأً يكلّفنا مالاً.
 *
 * فما يُعَدّ يُعَدّ لحظياً من قاعدة المشترك، وما لا يُعَدّ يُقرأ من
 * العدّاد الدوري. والفصل بينهما في `COUNTABLE` أدناه.
 */
final class Quota
{
    /**
     * الحدود التي تُعَدّ لحظياً: المفتاح ← [الجدول، شرط اختياري].
     *
     * @var array<string, array{0:string, 1:?array{0:string,1:mixed}}>
     */
    private const COUNTABLE = [
        'students' => ['users', ['role', 'student']],
        'instructors' => ['users', ['role', 'instructor']],
        'staff' => ['users', ['role', 'staff']],
        'courses' => ['courses', null],
        'branches' => ['center_branches', null],
        'groups' => ['center_groups', null],
    ];

    private const STORAGE = 'storage_gb';

    private const BYTES_PER_GB = 1_073_741_824;

    public function __construct(private readonly ?Tenant $tenant) {}

    /**
     * يمنع تجاوز الحدّ قبل وقوعه.
     *
     * @throws QuotaExceededException
     */
    public function enforce(string $feature, int $adding = 1): void
    {
        $limit = $this->limit($feature);

        if ($limit === null) {
            return;
        }

        $used = $this->used($feature);

        if ($used + $adding > $limit) {
            throw new QuotaExceededException($feature, $used, $limit);
        }
    }

    /** هل يتّسع للإضافة؟ — للعرض في الواجهة قبل فتح النموذج */
    public function fits(string $feature, int $adding = 1): bool
    {
        $limit = $this->limit($feature);

        return $limit === null || $this->used($feature) + $adding <= $limit;
    }

    /** null تعني بلا حد */
    public function limit(string $feature): ?int
    {
        if ($this->tenant === null) {
            return null;
        }

        $limit = $this->tenant->entitlements()->limit($feature);

        // صفر يعني «ممنوع» لا «بلا حدّ»، والفرق بينهما هو الفرق بين المنع والإباحة
        return $limit;
    }

    public function used(string $feature): int
    {
        if ($this->tenant === null) {
            return 0;
        }

        if ($feature === self::STORAGE) {
            return $this->storageUsedGb();
        }

        $countable = self::COUNTABLE[$feature] ?? null;

        if ($countable === null) {
            // ما لا يُعَدّ: الإيميلات ودقائق الفيديو — عدّادها الدوري
            return $this->tenant->entitlements()->usage($feature);
        }

        [$table, $where] = $countable;

        return $this->tenant->run(function () use ($table, $where): int {
            if (! Schema::hasTable($table)) {
                return 0;
            }

            $query = DB::table($table);

            if ($where !== null) {
                $query->where($where[0], $where[1]);
            }

            return (int) $query->count();
        });
    }

    public function remaining(string $feature): ?int
    {
        $limit = $this->limit($feature);

        return $limit === null ? null : max(0, $limit - $this->used($feature));
    }

    /** ٠..١٠٠، أو null لما لا حدّ له */
    public function percent(string $feature): ?float
    {
        $limit = $this->limit($feature);

        if ($limit === null || $limit === 0) {
            return null;
        }

        return round(min(100, $this->used($feature) / $limit * 100), 1);
    }

    /**
     * كل الحدود الرقمية بحالتها — تغذّي شاشة «استهلاكي».
     *
     * @return list<array{key:string, used:int, limit:?int, percent:?float, countable:bool}>
     */
    public function overview(): array
    {
        $keys = [...array_keys(self::COUNTABLE), self::STORAGE, 'video_minutes', 'emails'];
        $rows = [];

        foreach ($keys as $key) {
            $limit = $this->limit($key);

            // ما لا تمنحه الباقة أصلاً لا يُعرض: صفرٌ من صفر ليس استهلاكاً
            if ($limit === 0) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'used' => $this->used($key),
                'limit' => $limit,
                'percent' => $this->percent($key),
                'countable' => isset(self::COUNTABLE[$key]) || $key === self::STORAGE,
            ];
        }

        return $rows;
    }

    /** يُسجَّل ما لا يُعَدّ فقط — تسجيل المعدود يُنشئ رقمين متضاربين */
    public function record(string $feature, int $delta = 1): void
    {
        if ($this->tenant === null || isset(self::COUNTABLE[$feature])) {
            return;
        }

        $this->tenant->entitlements()->recordUsage($feature, $delta);
    }

    public static function isCountable(string $feature): bool
    {
        return isset(self::COUNTABLE[$feature]);
    }

    /**
     * التخزين يُفحص بالبايت لا بالجيجا.
     *
     * الحدّ مكتوب بالجيجا والملفات بالميجا، فتحويل كل ملف إلى جيجا
     * صحيحة يجعل كل ما دون الجيجا صفراً — وحدٌّ يُضاف إليه صفرٌ
     * أبداً لا يُبلَغ أبداً. فيُقارَن المجموع الحقيقي بالبايت،
     * ويبقى العرض بالجيجا لأنها ما يفهمه المشترك.
     *
     * @throws QuotaExceededException
     */
    public function enforceStorage(int $addingBytes): void
    {
        $limitGb = $this->limit(self::STORAGE);

        if ($limitGb === null) {
            return;
        }

        $limitBytes = $limitGb * self::BYTES_PER_GB;
        $usedBytes = $this->storageUsedBytes();

        if ($usedBytes + $addingBytes > $limitBytes) {
            throw new QuotaExceededException(
                self::STORAGE,
                (int) ceil($usedBytes / self::BYTES_PER_GB),
                $limitGb,
            );
        }
    }

    private function storageUsedBytes(): int
    {
        return $this->tenant->run(function (): int {
            return Schema::hasTable('media') ? (int) DB::table('media')->sum('size') : 0;
        });
    }

    /** للعرض: يُقرَّب للأعلى، فمن استهلك ٤٫٢ جيجا في باقة ٥ يرى ٥ لا ٤ */
    private function storageUsedGb(): int
    {
        return (int) ceil($this->storageUsedBytes() / self::BYTES_PER_GB);
    }
}
