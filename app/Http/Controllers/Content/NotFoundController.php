<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Core\Access\Ability;
use App\Modules\Content\Models\NotFoundLog;
use App\Modules\Content\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الروابط المكسورة — قائمة عملٍ لا تقرير.
 *
 * معرفةُ الرابط الميت بلا إصلاحه لا تنفع. فمن الشاشة نفسها تُنشأ
 * التحويلة ٣٠١ ويُوسَم السطر مُصلَحاً.
 */
final class NotFoundController
{
    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('admin.not-found-logs', [
            /*
             | الأكثر طلباً أولاً لا الأحدث.
             |
             | رابطٌ طُلب ألف مرّة يُخسّر ألف زائر، وآخرُ طُلب مرّةً
             | قد يكون خطأً مطبعياً من زائرٍ واحد.
             */
            'rows' => NotFoundLog::where('is_resolved', false)
                ->orderByDesc('hits')->orderByDesc('last_seen_at')
                ->paginate(40),

            'resolved' => NotFoundLog::where('is_resolved', true)->count(),
        ]);
    }

    /** يُنشئ التحويلة ويُغلق السطر */
    public function resolve(Request $request, int $id): RedirectResponse
    {
        $this->authorise($request);

        $row = NotFoundLog::findOrFail($id);

        $input = $request->validate([
            'to' => ['required', 'string', 'max:2000'],
        ]);

        Redirect::updateOrCreate(
            ['from' => '/'.ltrim($row->path, '/')],
            // العمود اسمه `code` لا `status` — والتحويلة الدائمة ٣٠١
            ['to' => (string) $input['to'], 'code' => 301],
        );

        $row->forceFill(['is_resolved' => true])->save();

        return back()->with('status', __('أُنشئت التحويلة، وصار الرابط يقود إلى وجهته.'));
    }

    /** يُغلق السطر بلا تحويلة — لروابطٍ لا تستحقّ */
    public function dismiss(Request $request, int $id): RedirectResponse
    {
        $this->authorise($request);

        NotFoundLog::whereKey($id)->update(['is_resolved' => true]);

        return back()->with('status', __('أُخفي السطر.'));
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::CONTENT_MANAGE), 403);
    }
}
