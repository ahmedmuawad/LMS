<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Access\Ability;
use App\Modules\Api\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * مفاتيح الواجهة البرمجية — إنشاؤها وإلغاؤها.
 */
final class ApiTokenController
{
    public function index(Request $request): View
    {
        $this->authorise($request);

        return view('tenant.api-tokens', [
            'tokens' => ApiToken::with('user')->latest('id')->get(),
            'scopes' => ApiToken::SCOPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorise($request);

        $input = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'in:'.implode(',', array_keys(ApiToken::SCOPES))],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        ['plain' => $plain] = ApiToken::issue(
            $request->user(),
            $input['name'],
            $input['scopes'],
            $input['expires_at'] ?? null,
        );

        /*
         | المفتاح يُعرض مرة واحدة.
         |
         | ولا يُخزَّن نصّاً، فلا سبيل إلى عرضه ثانية — وهذا يُقال
         | للمستخدم قبل أن يغادر الصفحة لا بعد أن يبحث عنه.
         */
        return back()
            ->with('status', __('أُنشئ المفتاح. انسخه الآن — لن يُعرض مرة أخرى.'))
            ->with('plain_token', $plain);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->authorise($request);

        ApiToken::findOrFail($id)->delete();

        return back()->with('status', __('أُلغي المفتاح. أي تكاملٍ يستعمله سيتوقّف فوراً.'));
    }

    private function authorise(Request $request): void
    {
        abort_unless($request->user()?->allows(Ability::SETTINGS_MANAGE), 403);

        abort_unless(
            tenant()?->allows('api_access') ?? false,
            402,
            __('الواجهة البرمجية غير متاحة في باقتك.'),
        );
    }
}
