<?php

declare(strict_types=1);

use App\Core\Theming\BrandPalette;

it('keeps white text on the brand fill readable', function (string $hex) {
    $palette = BrandPalette::fromHex($hex);

    expect(BrandPalette::contrast($palette->fill, '#FFFFFF'))->toBeGreaterThanOrEqual(4.5);
})->with(['#1F6FEB', '#FFD400', '#7CFC00', '#00FFFF', '#FFFFFF', '#FF69B4']);

it('darkens the brand further for text on a light surface', function () {
    $palette = BrandPalette::fromHex('#FFD400');

    expect(BrandPalette::contrast($palette->text, '#FFFFFF'))->toBeGreaterThanOrEqual(7.0);
});

it('gives the hover state a visibly deeper shade', function () {
    $palette = BrandPalette::fromHex('#1F6FEB');

    expect($palette->hover)->not->toBe($palette->fill);
});

it('falls back to the house colour rather than breaking on nonsense', function () {
    expect(BrandPalette::fromHex('not-a-colour')->fill)->toStartWith('#');
});

it('accepts the three-digit shorthand', function () {
    expect(BrandPalette::fromHex('#0af')->fill)->toStartWith('#');
});
