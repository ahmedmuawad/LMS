<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Core\Onboarding\OnboardingWizard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OnboardingController
{
    public function __construct(private readonly OnboardingWizard $wizard) {}

    public function show(string $step = 'mode'): View|RedirectResponse
    {
        if ($this->wizard->isComplete()) {
            return redirect(url('/admin/dashboard'));
        }

        if (! $this->wizard->canView($step)) {
            return redirect(url('/onboarding/'.$this->wizard->currentStep()));
        }

        return view('onboarding.'.$step, [
            'wizard' => $this->wizard,
            'step' => $step,
            'tenant' => tenant(),
        ]);
    }

    public function store(Request $request, string $step): RedirectResponse
    {
        $tenant = tenant();

        match ($step) {
            'mode' => $this->wizard->saveMode(
                $tenant,
                $request->validate([
                    'mode' => ['required', 'in:'.implode(',', array_keys(config('platform-modes.modes')))],
                ])['mode'],
                $request->boolean('center_enabled'),
            ),

            'delivery' => $this->wizard->saveDelivery($tenant, $request->validate([
                'delivery' => ['required', 'in:'.implode(',', array_keys(config('platform-modes.delivery')))],
            ])['delivery']),

            'identity' => $this->wizard->saveIdentity($tenant, $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'tagline' => ['nullable', 'string', 'max:180'],
                'theme' => ['required', 'string', 'max:64'],
                'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ])),

            'locale' => $this->wizard->saveLocale($tenant, $request->validate([
                'locale' => ['required', 'in:'.implode(',', array_keys(config('locales.supported')))],
                'country' => ['required', 'exists:'.config('tenancy.database.central_connection').'.countries,code'],
                'currency' => ['required', 'exists:'.config('tenancy.database.central_connection').'.currencies,code'],
                'numerals' => ['required', 'in:arabic,hindi'],
            ])),

            default => throw new NotFoundHttpException("خطوة غير معروفة: [{$step}]"),
        };

        return redirect(url('/onboarding/'.$this->wizard->currentStep()));
    }

    public function finish(): RedirectResponse
    {
        $this->wizard->complete();

        return redirect(url('/admin/dashboard'))
            ->with('status', __('منصّتك جاهزة. ابدأ بإضافة أول كورس.'));
    }

    /** @return array<string, string> */
    public static function countries(): array
    {
        return DB::connection(config('tenancy.database.central_connection'))
            ->table('countries')->where('is_active', true)->orderBy('position_order')
            ->pluck('name', 'code')
            ->map(fn (string $json): string => json_decode($json, true)[app()->getLocale()] ?? '')
            ->all();
    }

    /** @return array<string, string> */
    public static function currencies(): array
    {
        return DB::connection(config('tenancy.database.central_connection'))
            ->table('currencies')->where('is_active', true)->orderBy('position_order')
            ->pluck('name', 'code')
            ->map(fn (string $json): string => json_decode($json, true)[app()->getLocale()] ?? '')
            ->all();
    }
}
