<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Modules\Content\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * آخر محطة قبل الـ404.
 *
 * شرط ترحيل: كل رابط من الموقع القديم يجب أن يصل إلى مقابله،
 * وإلا ضاع ترتيب سنوات في جوجل عند أول يوم إطلاق.
 */
final class RedirectController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $path = '/'.trim($request->path(), '/');

        $redirect = Redirect::where('from', $path)
            ->orWhere('from', rtrim($path, '/').'/')
            ->first();

        if ($redirect === null) {
            throw new NotFoundHttpException;
        }

        // العدّاد يقول أي روابط قديمة ما زالت حيّة فعلاً
        $redirect->forceFill([
            'hits' => (int) $redirect->hits + 1,
            'last_hit_at' => now(),
        ])->saveQuietly();

        $target = str_starts_with($redirect->to, 'http') ? $redirect->to : url($redirect->to);

        return redirect()->away($target, in_array((int) $redirect->code, [301, 302, 307, 308], true) ? (int) $redirect->code : 301);
    }
}
