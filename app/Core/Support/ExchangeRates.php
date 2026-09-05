<?php

declare(strict_types=1);

namespace App\Core\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * أسعار الصرف: تحديثها، والتحويل بها، وتقريب الناتج.
 *
 * ## لماذا لا تُترك يدوية
 *
 * جدول العملات فيه `rate_to_base` منذ البداية، ولا شيء يُحدّثه —
 * فسعرٌ كُتب مرّةً يبقى سنةً، ويُباع كورسٌ بالريال بسعر العام
 * الماضي. والفرق يخرج من جيب المشترك بلا أن يدري.
 *
 * ## والتقريب النفسي بعد التحويل
 *
 * ١٩٩ جنيهاً تصير ٢٩٫٤٧ ريالاً — ورقمٌ كهذا يبدو آلياً لا مسعّراً.
 * وقاعدة التقريب في الجدول (`.99` أو `.00` أو `nearest_9`) تجعله
 * ٢٩٫٩٩ أو ٣٠ أو ٢٩ — كما يسعّر البشر.
 *
 * ## والسعر يُجمَّد في السلة
 *
 * من رأى ٢٩٫٩٩ وذهب يُحضر بطاقته لا يعود ليجد ٣٠٫٤٠. فالسعر
 * يُثبَّت نصف ساعة — وهي مدّةٌ تكفي للدفع ولا تُبقي سعراً قديماً
 * إلى الغد.
 */
final class ExchangeRates
{
    /** مدّة تجميد السعر في السلة */
    public const FREEZE_MINUTES = 30;

    /**
     * يُحدّث الأسعار من مزوّد مجاني بلا مفتاح.
     *
     * ونستعمل واحداً بلا تسجيل عمداً: مفتاحٌ يُطلب من صاحب المنصّة
     * لأجل عملةٍ ثانية بابٌ يقف عنده — فلا يُحدَّث السعر أبداً.
     *
     * @return array{updated:int, base:string}
     *
     * @throws RuntimeException
     */
    public function refresh(): array
    {
        $base = $this->base();

        $response = Http::timeout(20)->get('https://open.er-api.com/v6/latest/'.$base);

        if ($response->failed() || $response->json('result') !== 'success') {
            throw new RuntimeException(__('تعذّر جلب أسعار الصرف الآن.'));
        }

        $rates = (array) $response->json('rates', []);
        $updated = 0;

        foreach (DB::connection($this->connection())->table('currencies')->get() as $currency) {
            $code = mb_strtoupper((string) $currency->code);

            if ($code === $base) {
                DB::connection($this->connection())->table('currencies')->where('code', $code)->update([
                    'rate_to_base' => 1,
                    'rate_updated_at' => now(),
                    'rate_source' => 'api',
                ]);

                $updated++;

                continue;
            }

            if (! isset($rates[$code]) || ! is_numeric($rates[$code])) {
                continue;
            }

            DB::connection($this->connection())->table('currencies')->where('code', $code)->update([
                'rate_to_base' => (float) $rates[$code],
                'rate_updated_at' => now(),
                'rate_source' => 'api',
            ]);

            $updated++;
        }

        return ['updated' => $updated, 'base' => $base];
    }

    /**
     * يحوّل مبلغاً من عملةٍ إلى أخرى ويُقرّبه بقاعدة الوجهة.
     *
     * ولا يُحوَّل شيء إن كانت العملتان واحدة: التحويل ذهاباً وإياباً
     * يُنقص قرشاً بالتقريب، ومبلغٌ يتغيّر بلا سبب يهدم الثقة.
     */
    public function convert(Money $amount, string $to): Money
    {
        $to = mb_strtoupper($to);

        if ($amount->currency === $to) {
            return $amount;
        }

        $from = $this->rate($amount->currency);
        $target = $this->rate($to);

        if ($from <= 0 || $target <= 0) {
            return $amount;   // سعرٌ مجهول: يُترك المبلغ كما هو ولا يُخترَع
        }

        $decimalsFrom = Money::decimalsFor($amount->currency);
        $decimalsTo = Money::decimalsFor($to);

        $value = ($amount->minor / (10 ** $decimalsFrom)) / $from * $target;

        return $this->round(Money::fromDecimal(number_format($value, $decimalsTo, '.', ''), $to));
    }

    /**
     * التقريب النفسي بقاعدة العملة.
     *
     * `none` يترك الرقم، و`.99` يجعله ينتهي بـ٩٩، و`.00` يرفعه إلى
     * الوحدة، و`nearest_9` يرفعه إلى أقرب رقمٍ ينتهي بتسعة.
     */
    public function round(Money $amount): Money
    {
        $rule = $this->rule($amount->currency);
        $decimals = Money::decimalsFor($amount->currency);
        $unit = 10 ** $decimals;

        if ($rule === 'none' || $unit === 1) {
            return $amount;
        }

        $units = (int) ceil($amount->minor / $unit);

        return match ($rule) {
            '.99' => Money::fromMinor(max(0, $units * $unit - 1), $amount->currency),
            '.00' => Money::fromMinor($units * $unit, $amount->currency),
            'nearest_9' => Money::fromMinor($this->endingInNine($units) * $unit, $amount->currency),
            default => $amount,
        };
    }

    /**
     * أقرب عددٍ ينتهي بتسعة — والأقرب لا الأعلى.
     *
     * الرفع دائماً يقفز بـ٢٤ إلى ٢٩ وبـ٤ إلى ٩، فيصير التحويل
     * زيادةَ سعرٍ لا تقريباً. والأقرب يجعل ٢٤ تصير ١٩ و٢٦ تصير ٢٩.
     *
     * وعند التساوي يُنزَل لا يُرفَع: مشترٍ يرى سعراً أعلى ممّا
     * حسبه يشكّ، ومن يراه أقلّ لا يشتكي.
     */
    private function endingInNine(int $units): int
    {
        if ($units % 10 === 9) {
            return $units;
        }

        $down = intdiv($units, 10) * 10 - 1;
        $up = intdiv($units, 10) * 10 + 9;

        if ($down < 9) {
            return $up;   // لا يوجد أدنى صالح: أصغر سعرٍ ينتهي بتسعة هو ٩
        }

        return ($units - $down) <= ($up - $units) ? $down : $up;
    }

    /** سعر العملة مقابل الأساس — تقرؤه السلة لتُجمّده */
    public function rateFor(string $code): float
    {
        return $this->rate($code);
    }

    private function rate(string $code): float
    {
        return (float) ($this->row($code)->rate_to_base ?? 0);
    }

    private function rule(string $code): string
    {
        return (string) ($this->row($code)->rounding_rule ?? 'none');
    }

    private function row(string $code): ?object
    {
        try {
            return DB::connection($this->connection())->table('currencies')
                ->where('code', mb_strtoupper($code))->first();
        } catch (Throwable $e) {
            Log::warning('تعذّرت قراءة العملة: '.$e->getMessage());

            return null;
        }
    }

    private function base(): string
    {
        $row = DB::connection($this->connection())->table('currencies')->where('is_base', true)->first();

        return mb_strtoupper((string) ($row->code ?? config('money.default', 'EGP')));
    }

    /** العملات مرجعٌ مركزي: واحدةٌ للمنصّة كلّها لا لكل مشترك */
    private function connection(): string
    {
        return config('tenancy.database.central_connection', 'sqlite');
    }
}
