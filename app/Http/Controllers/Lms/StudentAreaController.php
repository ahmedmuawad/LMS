<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lms;

use App\Modules\Commerce\Models\Order;
use App\Modules\Gamification\Models\Badge;
use App\Modules\Lms\Models\Certificate;
use App\Modules\Lms\Models\Note;
use App\Modules\Lms\Models\Wishlist;
use App\Modules\Services\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * شاشات لوحة الطالب الستّ.
 *
 * كانت قائمة الطالب تعرض روابطها قبل بنائها، فيقع على ٤٠٤ من
 * يضغطها. القاعدة التي كُسرت هنا: لا يُعرض في قائمة رابطٌ لا
 * تفتحه شاشة.
 *
 * وكل استعلام هنا مقيّد بـ `user_id` الجالس: ملفّ الطالب لا يُقرأ
 * بمعرّفٍ يصل من الطلب.
 */
final class StudentAreaController
{
    public function certificates(Request $request): View
    {
        return view('lms.student.certificates', [
            'certificates' => Certificate::where('user_id', $request->user()->getKey())
                ->with('course')
                ->orderByDesc('issued_at')
                ->get(),
        ]);
    }

    public function badges(Request $request): View
    {
        $userId = $request->user()->getKey();

        $mine = DB::table('badge_user')->where('user_id', $userId)
            ->pluck('awarded_at', 'badge_id');

        /*
         | الشارات غير المنالة تُعرض باهتةً لا تُخفى.
         |
         | الشارة التي لا يعرف الطالب بوجودها لا تحفّزه على شيء —
         | وهذا عكس قائمة الميزات، حيث الإخفاء أرحم لأن الطالب لا
         | يملك شراءها.
         */
        return view('lms.student.badges', [
            'badges' => Badge::where('is_active', true)
                ->orderByDesc('points')
                ->get()
                ->map(fn (Badge $badge): array => [
                    'badge' => $badge,
                    'awarded_at' => $mine[$badge->getKey()] ?? null,
                ]),
            'earned' => $mine->count(),
        ]);
    }

    public function orders(Request $request): View
    {
        return view('lms.student.orders', [
            'orders' => Order::where('user_id', $request->user()->getKey())
                ->with('items')
                ->latest('placed_at')
                ->paginate(15),
        ]);
    }

    public function services(Request $request): View
    {
        return view('lms.student.services', [
            'bookings' => Booking::where('user_id', $request->user()->getKey())
                ->with(['service', 'provider'])
                ->orderByDesc('date')->orderByDesc('starts_at')
                ->paginate(15),
        ]);
    }

    // ---------------------------------------------------------------
    // الملاحظات
    // ---------------------------------------------------------------

    public function notes(Request $request): View
    {
        return view('lms.student.notes', [
            'notes' => Note::ownedBy($request->user()->getKey())
                ->with(['course', 'lesson'])
                ->orderByDesc('is_pinned')->latest('updated_at')
                ->paginate(20),
        ]);
    }

    public function storeNote(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'at_second' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        $request->user()->notes()->create($input);

        return back()->with('status', __('حُفظت الملاحظة.'));
    }

    public function updateNote(Request $request, int $id): RedirectResponse
    {
        // النطاق أولاً ثم البحث: `findOrFail` وحده يكشف وجود ملاحظة غيرك
        $note = Note::ownedBy($request->user()->getKey())->findOrFail($id);

        $note->update($request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_pinned' => ['nullable', 'boolean'],
        ]) + ['is_pinned' => $request->boolean('is_pinned')]);

        return back()->with('status', __('حُدّثت الملاحظة.'));
    }

    public function destroyNote(Request $request, int $id): RedirectResponse
    {
        Note::ownedBy($request->user()->getKey())->findOrFail($id)->delete();

        return back()->with('status', __('حُذفت الملاحظة.'));
    }

    // ---------------------------------------------------------------
    // قائمة الأمنيات
    // ---------------------------------------------------------------

    public function wishlist(Request $request): View
    {
        $rows = Wishlist::ownedBy($request->user()->getKey())->latest()->get();

        /*
         | استعلامٌ لكل نوع لا لكل صفّ.
         |
         | `Wishlist::item()` تسأل القاعدة مرة لكل أمنية: قائمةٌ
         | فيها أربعون عنصراً تعني أربعين استعلاماً. وهي ثلاثة أنواع
         | لا أكثر، فيُجلب كلٌّ منها دفعةً واحدة.
         */
        $loaded = $rows->groupBy('itemable_type')->map(function ($group, string $type) {
            $model = Wishlist::modelFor($type);

            return $model === null
                ? collect()
                : $model::whereKey($group->pluck('itemable_id'))->get()->keyBy('id');
        });

        $items = $rows
            ->map(fn (Wishlist $row): array => [
                'row' => $row,
                'item' => $loaded[$row->itemable_type][$row->itemable_id] ?? null,
            ])
            // العنصر المحذوف يُطرح: بطاقة فارغة أسوأ من غيابها
            ->filter(fn (array $entry): bool => $entry['item'] !== null)
            ->values();

        return view('lms.student.wishlist', ['items' => $items]);
    }

    public function toggleWishlist(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(Wishlist::TYPES))],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $model = Wishlist::modelFor($input['type']);

        // وجود العنصر يُتحقّق منه: الأمنية على معرّفٍ لا وجود له سطرٌ ميت
        abort_unless($model !== null && $model::whereKey($input['id'])->exists(), 404);

        $existing = Wishlist::ownedBy($request->user()->getKey())
            ->where('itemable_type', $input['type'])
            ->where('itemable_id', $input['id'])
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return back()->with('status', __('أُزيلت من قائمة أمنياتك.'));
        }

        $item = $model::find($input['id']);

        $request->user()->wishlist()->create([
            'itemable_type' => $input['type'],
            'itemable_id' => $input['id'],
            'price_minor_at_add' => $item->price_minor ?? null,
            'currency' => $item->currency ?? null,
        ]);

        return back()->with('status', __('أُضيفت إلى قائمة أمنياتك.'));
    }
}
