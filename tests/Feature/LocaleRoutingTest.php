<?php

declare(strict_types=1);

// ADR-003 — العربية بلا بادئة، والإنجليزية تحت /en/.
// هذا الاختبار يحرس أهم قرار سيو في المشروع.

it('serves Arabic without a locale prefix', function () {
    $this->get('/design-system')
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl"', false);
});

it('serves English under the /en prefix', function () {
    $this->get('/en/design-system')
        ->assertOk()
        ->assertSee('<html lang="en" dir="ltr"', false);
});

it('keeps unprefixed URLs working for the home page', function () {
    $this->get('/')->assertOk();
});

it('does not expose an /ar prefix', function () {
    $this->get('/ar/design-system')->assertNotFound();
});
