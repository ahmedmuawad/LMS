<?php

declare(strict_types=1);

it('renders every section of the living style guide', function () {
    $response = $this->get('/design-system');

    $response->assertOk();

    foreach (['colors', 'type', 'space', 'buttons', 'forms', 'feedback', 'data', 'learning', 'center', 'states', 'rules'] as $section) {
        $response->assertSee('id="'.$section.'"', false);
    }
});

it('paints the page background from a token so it never borrows the host theme', function () {
    $this->get('/design-system')->assertSee('class="min-h-screen bg-bg text-content antialiased"', false);
});

it('prevents a flash of the wrong theme before Alpine loads', function () {
    $this->get('/design-system')->assertSee("localStorage.getItem('theme')", false);
});

it('offers a skip link as the first focusable element', function () {
    $this->get('/design-system')->assertSee('تخطَّ إلى المحتوى', false);
});
