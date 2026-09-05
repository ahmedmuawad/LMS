<?php

declare(strict_types=1);

namespace App\Modules\Lms\Actions;

use App\Modules\Lms\Models\Question;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * استيراد أسئلة من ملفّ CSV.
 *
 * ## لماذا CSV لا Word ولا PDF
 *
 * المدرّس عنده أسئلته في ملفّ Word غالباً، وقراءةُ Word تحتاج مكتبة
 * تفكّ ملفّاتٍ يرفعها المستخدم — وهي سطحُ هجومٍ أوسع ممّا تستحقّه
 * راحةٌ يحلّها «حفظ باسم CSV» من نفس البرنامج.
 *
 * وCSV يفتحه إكسل وGoogle Sheets، ويعرفه كل من أدار كشف درجات.
 *
 * ## والفاصل يُكتشف ولا يُفرَض
 *
 * إكسل العربي يحفظ بالفاصلة المنقوطة لا بالفاصلة، ومن يحفظ ملفّه
 * منه يجد كل صفٍّ عموداً واحداً — ويظنّ الملفّ تالفاً.
 *
 * ## وصفٌّ فاسد لا يُوقف الباقي
 *
 * ملفٌّ فيه مئة سؤال وسطرٌ ناقص يُنتج تسعةً وتسعين، ويُقال للمدرّس
 * أيّ سطرٍ سقط ولماذا. ورفضُ المئة لأجل واحدٍ يُضيّع عملاً صحيحاً.
 */
final class ImportQuestions
{
    /** ترويسات الملفّ — بالعربية كما يقرؤها المدرّس */
    public const HEADERS = ['النوع', 'السؤال', 'الخيارات', 'الإجابة', 'الشرح', 'الصعوبة', 'الدرجة'];

    /** أسماء الأنواع بالعربية → مفاتيحها */
    private const TYPES = [
        'اختيار واحد' => 'single_choice',
        'اختيار متعدد' => 'multiple_choice',
        'اختيار متعدّد' => 'multiple_choice',
        'صح وخطأ' => 'true_false',
        'صح او خطأ' => 'true_false',
        'أكمل الفراغ' => 'fill_blank',
        'اكمل الفراغ' => 'fill_blank',
        'إجابة قصيرة' => 'short_text',
        'اجابة قصيرة' => 'short_text',
        'مقالي' => 'essay',
    ];

    private const DIFFICULTIES = ['سهل' => 'easy', 'متوسط' => 'medium', 'صعب' => 'hard'];

    /** حدٌّ للصفوف: ملفٌّ بمئة ألف سطر يُسقط الطلب لا يُثريه */
    private const MAX_ROWS = 2000;

    /**
     * @return array{created:int, skipped:int, errors:list<string>}
     *
     * @throws RuntimeException
     */
    public function handle(string $csv, ?int $poolId = null): array
    {
        $rows = $this->parse($csv);

        if ($rows === []) {
            throw new RuntimeException(__('الملفّ فارغ أو لا يحتوي صفوفاً.'));
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $poolId, &$created, &$skipped, &$errors): void {
            foreach ($rows as $number => $row) {
                $problem = $this->validate($row);

                if ($problem !== null) {
                    $skipped++;

                    // عشرة أخطاء تكفي: قائمةٌ بمئة سطرٍ فاسد لا تُقرأ
                    if (count($errors) < 10) {
                        $errors[] = __('السطر :line: :why', ['line' => $number, 'why' => $problem]);
                    }

                    continue;
                }

                $this->create($row, $poolId);
                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    /** ملفّ القالب — يُنزَّل فيُملأ ويُرفع */
    public function template(): string
    {
        $rows = [
            self::HEADERS,
            ['اختيار واحد', 'ما عاصمة مصر؟', 'القاهرة|الإسكندرية|أسوان|طنطا', 'القاهرة', 'القاهرة هي العاصمة منذ ١٩٥٨', 'سهل', '1'],
            ['اختيار متعدّد', 'أيٌّ مما يلي من الغازات النبيلة؟', 'هيليوم|نيون|أكسجين|أرجون', 'هيليوم|نيون|أرجون', '', 'متوسط', '2'],
            ['صح وخطأ', 'الماء يغلي عند ١٠٠ درجة مئوية عند مستوى سطح البحر.', '', 'صح', '', 'سهل', '1'],
            ['أكمل الفراغ', 'عدد أضلاع المثلث ......', '', '3|ثلاثة', 'يُقبل الرقم أو الكلمة', 'سهل', '1'],
            ['مقالي', 'اشرح أسباب الثورة الصناعية.', '', '', 'يصحّحه المدرّس بنفسه', 'صعب', '10'],
        ];

        $csv = "\u{FEFF}";   // BOM: بغيره تُقرأ العربية رموزاً في إكسل

        foreach ($rows as $row) {
            $csv .= implode(',', array_map(
                fn (string $cell): string => '"'.str_replace('"', '""', $cell).'"',
                $row,
            ))."\n";
        }

        return $csv;
    }

    /**
     * @return array<int, array<string, string>>
     *
     * @throws RuntimeException
     */
    private function parse(string $csv): array
    {
        $csv = ltrim($csv, "\u{FEFF}");
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];

        if ($lines === []) {
            return [];
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn (string $h): string => trim($h), str_getcsv($lines[0], $delimiter, '"', ''));

        if (count($header) < 4) {
            throw new RuntimeException(__('الترويسة غير مفهومة — نزّل القالب واملأه.'));
        }

        $out = [];

        foreach (array_slice($lines, 1) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            if (count($out) >= self::MAX_ROWS) {
                throw new RuntimeException(__('الملفّ أكبر من :max سؤال — قسّمه.', ['max' => self::MAX_ROWS]));
            }

            $cells = str_getcsv($line, $delimiter, '"', '');
            $row = [];

            foreach ($header as $position => $name) {
                $row[$name] = trim((string) ($cells[$position] ?? ''));
            }

            $out[$index + 2] = $row;   // رقم السطر كما يراه المدرّس في إكسل
        }

        return $out;
    }

    /** @param array<string, string> $row */
    private function validate(array $row): ?string
    {
        if (($row['السؤال'] ?? '') === '') {
            return __('لا نصّ للسؤال');
        }

        $type = $this->type($row);

        if ($type === null) {
            return __('نوع غير معروف: :type', ['type' => $row['النوع'] ?? '']);
        }

        if (in_array($type, Question::MANUAL_TYPES, true)) {
            return null;   // المقالي بلا إجابة — يصحّحه المدرّس
        }

        if (($row['الإجابة'] ?? '') === '') {
            return __('لا إجابة صحيحة');
        }

        if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
            $options = $this->split($row['الخيارات'] ?? '');

            if (count($options) < 2) {
                return __('الخيارات أقلّ من اثنين');
            }

            foreach ($this->split($row['الإجابة'] ?? '') as $answer) {
                if (! in_array($answer, $options, true)) {
                    return __('الإجابة «:answer» ليست من الخيارات', ['answer' => $answer]);
                }
            }
        }

        return null;
    }

    /** @param array<string, string> $row */
    private function create(array $row, ?int $poolId): void
    {
        $type = (string) $this->type($row);
        $options = $this->split($row['الخيارات'] ?? '');
        $answers = $this->split($row['الإجابة'] ?? '');

        /*
         | الخيارات تُخزَّن بمفاتيح a, b, c…
         |
         | والمدرّس يكتب نصّها لا مفتاحها — فمن يكتب «القاهرة» في
         | عمود الإجابة يقصد الخيار لا حرفه. فيُترجَم النصّ إلى مفتاحه.
         */
        $keyed = [];
        $keys = [];

        foreach (array_values($options) as $index => $label) {
            $key = chr(ord('a') + $index);
            $keyed[$key] = $label;
            $keys[$label] = $key;
        }

        $correct = match ($type) {
            'single_choice', 'multiple_choice' => array_values(array_map(
                fn (string $a): string => $keys[$a] ?? $a,
                $answers,
            )),
            'true_false' => [in_array(mb_strtolower($answers[0] ?? ''), ['صح', 'صحيح', 'true', '1', 'نعم'], true) ? '1' : '0'],
            default => $answers,
        };

        Question::create([
            'category_id' => $poolId,
            'type' => $type,
            'title' => mb_substr(strip_tags((string) $row['السؤال']), 0, 80),
            'body' => ['ar' => (string) $row['السؤال']],
            'options' => $keyed !== [] ? $keyed : null,
            'correct' => in_array($type, Question::MANUAL_TYPES, true) ? null : $correct,
            'explanation' => ($row['الشرح'] ?? '') !== '' ? ['ar' => (string) $row['الشرح']] : null,
            'difficulty' => self::DIFFICULTIES[$row['الصعوبة'] ?? ''] ?? 'medium',
            'marks' => max(0.5, (float) ($row['الدرجة'] ?? 1)),
        ]);
    }

    /** @param array<string, string> $row */
    private function type(array $row): ?string
    {
        $given = trim((string) ($row['النوع'] ?? ''));

        return self::TYPES[$given]
            ?? (array_key_exists($given, Question::TYPES) ? $given : null);
    }

    /**
     * الخيارات والإجابات تُفصَل بـ`|`.
     *
     * لا بالفاصلة: نصّ الخيار نفسه قد يحوي فاصلة («القاهرة، مصر»)،
     * والعمود كلّه بين علامتي اقتباس فلا يفصله محلّل CSV.
     *
     * @return list<string>
     */
    private function split(string $value): array
    {
        return array_values(array_filter(array_map(
            fn (string $part): string => trim($part),
            explode('|', $value),
        ), fn (string $part): bool => $part !== ''));
    }
}
