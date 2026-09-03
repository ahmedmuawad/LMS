<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Onboarding\OnboardingWizard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * لا لوحة قبل إكمال التهيئة: لوحة نصف مهيّأة تربك المشترك أكثر
 * مما تفيده. الموقع العام يعمل طبيعياً لطلابه في كل الأحوال.
 */
final class RequireOnboarding
{
    public function __construct(private readonly OnboardingWizard $wizard) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->wizard->isComplete()) {
            return redirect(url('/onboarding/'.$this->wizard->currentStep()));
        }

        return $next($request);
    }
}
