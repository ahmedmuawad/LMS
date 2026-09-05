<?php

declare(strict_types=1);

namespace App\Core\Entitlements\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * بلغ المشترك حدّ باقته.
 *
 * ترتدّ رسالةً تقول ما الحدّ وكم استُهلك وأين يُرقّي — لا خطأ ٤٠٣
 * عارياً. المشترك الذي يصطدم بحدٍّ لا يفهمه يظنّ النظام معطّلاً
 * فيتصل بالدعم؛ والذي يفهمه يرقّي أو يحذف. وكلاهما خيرٌ من مكالمة.
 */
final class QuotaExceededException extends RuntimeException
{
    /** @var array<string, string> */
    private const LABELS = [
        'students' => 'الطلبة',
        'instructors' => 'المدرّسون',
        'staff' => 'الموظّفون',
        'courses' => 'الكورسات',
        'branches' => 'الفروع',
        'groups' => 'المجموعات',
        'storage_gb' => 'مساحة التخزين',
        'video_minutes' => 'دقائق الفيديو',
        'emails' => 'الإيميلات',
    ];

    public function __construct(
        public readonly string $feature,
        public readonly int $used,
        public readonly int $limit,
    ) {
        parent::__construct(sprintf('quota exceeded: %s (%d/%d)', $feature, $used, $limit));
    }

    public function label(): string
    {
        return __(self::LABELS[$this->feature] ?? $this->feature);
    }

    public function forHumans(): string
    {
        return __('بلغتَ حدّ باقتك من :feature (:used من :limit). احذف ما لم يعد مستخدماً، أو رقِّ باقتك.', [
            'feature' => $this->label(),
            'used' => number_format($this->used),
            'limit' => number_format($this->limit),
        ]);
    }

    public function render(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->forHumans(),
                'feature' => $this->feature,
                'used' => $this->used,
                'limit' => $this->limit,
                'upgrade_url' => url('/admin/billing'),
            ], 402); // Payment Required — أدقّ من ٤٠٣: المنع مالي لا صلاحيّاتي
        }

        return back()
            ->withInput()
            ->with('quota_exceeded', [
                'message' => $this->forHumans(),
                'feature' => $this->feature,
                'used' => $this->used,
                'limit' => $this->limit,
            ]);
    }
}
