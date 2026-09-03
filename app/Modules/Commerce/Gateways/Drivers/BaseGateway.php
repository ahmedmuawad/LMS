<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Gateways\Drivers;

use App\Core\Support\Money;
use App\Modules\Commerce\Gateways\PaymentGateway;
use App\Modules\Commerce\Gateways\PaymentResult;
use Illuminate\Http\Request;

/**
 * ما تشترك فيه كل البوابات: قراءة إعداداتها من إعدادات المشترك،
 * وفحص العملة والدولة والحدّين الأدنى والأعلى.
 */
abstract class BaseGateway implements PaymentGateway
{
    public function isReady(): bool
    {
        return (bool) $this->setting('enabled', false);
    }

    public function supports(Money $amount, ?string $country = null): bool
    {
        $currencies = (array) $this->setting('currencies', []);

        if ($currencies !== [] && ! in_array($amount->currency, $currencies, true)) {
            return false;
        }

        $countries = array_filter((array) $this->setting('countries', []));

        if ($countries !== [] && $country !== null && ! in_array($country, $countries, true)) {
            return false;
        }

        $min = (int) $this->setting('min', 0) * 100;
        $max = (int) $this->setting('max', 0) * 100;

        if ($min > 0 && $amount->minor < $min) {
            return false;
        }

        return ! ($max > 0 && $amount->minor > $max);
    }

    public function title(): string
    {
        return (string) (setting()->translated('payments.'.$this->key().'_title') ?: $this->defaultTitle());
    }

    public function description(): ?string
    {
        return setting()->translated('payments.'.$this->key().'_description');
    }

    public function icon(): ?string
    {
        return $this->setting('icon');
    }

    public function isTestMode(): bool
    {
        return $this->setting('mode', 'test') !== 'live';
    }

    /** رسوم البوابة تُضاف على العميل إن اختار المشترك ذلك. */
    public function feeOn(Money $amount): Money
    {
        $percent = (float) $this->setting('fee_percent', 0);
        $fixed = (int) $this->setting('fee_fixed', 0) * 100;

        $fee = $percent > 0 ? $amount->percentage($percent) : Money::zero($amount->currency);

        return $fixed > 0 ? $fee->plus(Money::fromMinor($fixed, $amount->currency)) : $fee;
    }

    public function handleCallback(Request $request): ?PaymentResult
    {
        return null;
    }

    abstract protected function defaultTitle(): string;

    protected function setting(string $name, mixed $default = null): mixed
    {
        return setting('payments.'.$this->key().'_'.$name, $default);
    }
}
