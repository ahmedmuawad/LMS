<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Core\Reports\ReportBuilder;
use App\Core\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** تقارير التعليم والمال والتسويق. */
final class ReportController
{
    private const TABS = ['learning', 'activity', 'financial', 'marketing'];

    public function __construct(private readonly ReportBuilder $reports) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->input('tab'), self::TABS, true)
            ? (string) $request->input('tab')
            : 'learning';

        [$from, $to] = $this->range($request);

        return view('reports.index', [
            'tab' => $tab,
            'from' => $from,
            'to' => $to,
            'preset' => (string) $request->input('preset', '30'),
            'data' => match ($tab) {
                'activity' => $this->reports->activity($from, $to),
                'financial' => $this->reports->financial($from, $to),
                'marketing' => $this->reports->marketing($from, $to),
                default => $this->reports->learning($from, $to),
            },
        ]);
    }

    /** تصدير CSV — الرقم في جدول أفضل من صورة رسم. */
    public function export(Request $request): Response
    {
        $tab = in_array($request->input('tab'), self::TABS, true)
            ? (string) $request->input('tab')
            : 'learning';

        [$from, $to] = $this->range($request);

        $data = match ($tab) {
            'activity' => $this->reports->activity($from, $to),
            'financial' => $this->reports->financial($from, $to),
            'marketing' => $this->reports->marketing($from, $to),
            default => $this->reports->learning($from, $to),
        };

        $rows = [];

        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $rows[] = [$key, (string) $value];
            } elseif ($value instanceof Money) {
                $rows[] = [$key, $value->format()];
            }
        }

        $csv = "\u{FEFF}";   // BOM: بغيره تُقرأ العربية رموزاً في إكسل

        foreach ($rows as $row) {
            $csv .= implode(',', array_map(
                fn (string $cell): string => '"'.str_replace('"', '""', $cell).'"',
                $row,
            ))."\n";
        }

        $name = 'report-'.$tab.'-'.$from->toDateString().'-'.$to->toDateString().'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    /** @return array{0:Carbon, 1:Carbon} */
    private function range(Request $request): array
    {
        $preset = (string) $request->input('preset', '30');

        if ($preset === 'custom' && $request->filled(['from', 'to'])) {
            $from = Carbon::parse($request->string('from')->value())->startOfDay();
            $to = Carbon::parse($request->string('to')->value())->endOfDay();

            // مدى مقلوب يُصحَّح بدل أن يُرجع جدولاً فارغاً بلا تفسير
            return $from->lte($to) ? [$from, $to] : [$to, $from];
        }

        $days = max(1, min(730, (int) $preset ?: 30));

        return [now()->subDays($days)->startOfDay(), now()->endOfDay()];
    }
}
