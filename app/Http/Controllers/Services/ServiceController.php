<?php

declare(strict_types=1);

namespace App\Http\Controllers\Services;

use App\Modules\Lms\Models\Taxonomy;
use App\Modules\Services\Actions\BookService;
use App\Modules\Services\Actions\FindSlots;
use App\Modules\Services\Models\Booking;
use App\Modules\Services\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;

/** واجهة الخدمات العامة: العرض ثم الحجز ثم متابعة الحجز. */
final class ServiceController
{
    public function index(Request $request): View
    {
        $services = Service::published()
            ->with(['cover', 'category'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('excerpt', 'like', $term));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->value()))
            ->orderByDesc('bookings_count')
            ->paginate(12)
            ->withQueryString();

        return view('services.index', [
            'services' => $services,
            'categories' => Taxonomy::ofType('category')->orderBy('position')->get(),
        ]);
    }

    public function show(Request $request, string $slug, FindSlots $slots): View
    {
        $service = Service::where('slug', $slug)->published()
            ->with(['cover', 'category', 'providers.user'])
            ->firstOrFail();

        // التقويم يُبنى للخدمات الموعدية وحدها — غيرها لا وقت لها تحجزه
        $calendar = $service->isBookable()
            ? $slots->handle($service, $this->from($request), (int) setting('services.calendar_days', 14))
            : [];

        return view('services.show', [
            'service' => $service,
            'calendar' => $calendar,
            'from' => $this->from($request),
        ]);
    }

    public function book(Request $request, string $slug, BookService $book): RedirectResponse
    {
        $service = Service::where('slug', $slug)->published()->firstOrFail();

        if ($request->user() === null && ! (bool) setting('services.guest_booking', true)) {
            return redirect(url('/login'))->with('status', __('سجّل دخولك لإتمام الحجز.'));
        }

        $rules = [
            'name' => [$request->user() === null ? 'required' : 'nullable', 'string', 'max:120'],
            'email' => [$request->user() === null ? 'required' : 'nullable', 'email', 'max:160'],
            'phone' => [(bool) setting('services.require_phone', false) ? 'required' : 'nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'intake' => ['nullable', 'array'],
            'intake.*' => ['nullable', 'string', 'max:2000'],
        ];

        if ($service->isBookable()) {
            $rules['provider_id'] = ['required', 'integer'];
            $rules['date'] = ['required', 'date'];
            $rules['starts_at'] = ['required', 'date_format:H:i'];
        }

        $input = $request->validate($rules);

        try {
            $booking = $book->handle($service, $input, $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect(url('/bookings/'.$booking->token))
            ->with('status', __('سجّلنا طلبك. رقم الحجز :reference', ['reference' => $booking->reference]));
    }

    /**
     * الرابط يحمل رمزاً عشوائياً لا رقم الحجز.
     *
     * الرقم متسلسل ليُقرأ في الهاتف، ولو كان هو العنوان لصار
     * تصفّح حجوزات الناس — بأسمائهم وبرقم هاتفهم — عدّاً تصاعدياً.
     */
    public function booking(Request $request, string $token): View
    {
        $booking = $this->findBooking($token);

        // صاحب الحجز المسجّل لا يراه أحد سواه ومن يدير اللوحة
        abort_unless(
            $booking->user_id === null
            || $booking->user_id === $request->user()?->getKey()
            || $request->user()?->canAccessPanel() === true,
            403,
        );

        return view('services.booking', ['booking' => $booking]);
    }

    public function mine(Request $request): View
    {
        return view('services.mine', [
            'bookings' => Booking::where('user_id', $request->user()->getKey())
                ->with(['service', 'provider.user'])
                ->orderByRaw('date is null')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function cancel(Request $request, string $token, BookService $book): RedirectResponse
    {
        $booking = $this->findBooking($token);

        if (! (bool) setting('services.allow_cancel', true) && $request->user()?->canAccessPanel() !== true) {
            return back()->withErrors(['booking' => __('الإلغاء الذاتي معطّل — راسلنا لإلغاء الحجز.')]);
        }

        abort_unless(
            $booking->user_id === $request->user()?->getKey() || $request->user()?->canAccessPanel() === true,
            403,
        );

        if (! $booking->canCancelFreely() && $request->user()?->canAccessPanel() !== true) {
            return back()->withErrors(['booking' => __('انتهت مهلة الإلغاء المجاني لهذا الحجز.')]);
        }

        try {
            $book->cancel($booking, $request->string('reason')->value() ?: null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', __('أُلغي الحجز.'));
    }

    /** يقبل الرمز، ويقبل رقم الحجز من يدير اللوحة وحده. */
    private function findBooking(string $token): Booking
    {
        return Booking::where('token', $token)
            ->when(
                request()->user()?->canAccessPanel() === true,
                fn ($q) => $q->orWhere('reference', $token),
            )
            ->with(['service.cover', 'provider.user'])
            ->firstOrFail();
    }

    private function from(Request $request): Carbon
    {
        $from = $request->filled('from') ? Carbon::parse($request->string('from')->value()) : now();

        // لا تقويم في الماضي: رابط قديم لا يجوز أن يعرض مواعيد فاتت
        return $from->lt(now()->startOfDay()) ? now() : $from;
    }
}
