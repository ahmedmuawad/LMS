<?php

declare(strict_types=1);

use App\Core\Support\Money;

it('stores amounts as integer minor units', function () {
    expect(Money::fromDecimal('125.50', 'EGP')->minor)->toBe(12550)
        ->and(Money::fromDecimal(0.1, 'EGP')->plus(Money::fromDecimal(0.2, 'EGP'))->toDecimal())->toBe('0.30');
});

it('respects per-currency decimals', function () {
    expect(Money::fromDecimal('12.345', 'KWD')->minor)->toBe(12345)
        ->and(Money::fromDecimal('12.345', 'KWD')->toDecimal())->toBe('12.345')
        ->and(Money::fromDecimal('1200', 'JPY')->minor)->toBe(1200)
        ->and(Money::fromDecimal('1200', 'JPY')->toDecimal())->toBe('1200');
});

it('truncates rather than rounds on input', function () {
    expect(Money::fromDecimal('10.999', 'EGP')->toDecimal())->toBe('10.99');
});

it('handles negative amounts', function () {
    expect(Money::fromDecimal('-50.25', 'EGP')->minor)->toBe(-5025)
        ->and(Money::fromDecimal('-50.25', 'EGP')->toDecimal())->toBe('-50.25')
        ->and(Money::fromDecimal('-50.25', 'EGP')->isNegative())->toBeTrue();
});

it('refuses to mix currencies', function () {
    Money::fromMinor(100, 'EGP')->plus(Money::fromMinor(100, 'SAR'));
})->throws(InvalidArgumentException::class);

it('rejects invalid currency codes', function () {
    Money::fromMinor(100, 'EGYPT');
})->throws(InvalidArgumentException::class);

it('allocates without losing a single minor unit', function () {
    $shares = Money::fromMinor(1000, 'EGP')->allocate([1, 1, 1]);

    expect(array_sum(array_map(fn (Money $m) => $m->minor, $shares)))->toBe(1000)
        ->and($shares[0]->minor)->toBe(334)
        ->and($shares[1]->minor)->toBe(333)
        ->and($shares[2]->minor)->toBe(333);
});

it('splits an order by instructor commission without drift', function () {
    // 750.00 ج.م مقسّمة 70% للمدرّس و30% للمنصة
    $shares = Money::fromDecimal('750.00', 'EGP')->allocate([70, 30]);

    expect($shares[0]->toDecimal())->toBe('525.00')
        ->and($shares[1]->toDecimal())->toBe('225.00')
        ->and($shares[0]->plus($shares[1])->toDecimal())->toBe('750.00');
});

it('calculates VAT correctly per country', function () {
    expect(Money::fromDecimal('1000', 'EGP')->percentage(14)->toDecimal())->toBe('140.00')
        ->and(Money::fromDecimal('1000', 'SAR')->percentage(15)->toDecimal())->toBe('150.00');
});

it('formats by locale', function () {
    app()->setLocale('ar');
    expect(Money::fromDecimal('1250', 'EGP')->format())->toBe('1,250.00 ج.م');

    app()->setLocale('en');
    expect(Money::fromDecimal('1250', 'USD')->format())->toBe('$ 1,250.00');
});
