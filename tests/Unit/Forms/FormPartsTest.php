<?php

/**
 * Unit tests for the parts a site draws a form from (spec 104 US3; issue #248).
 *
 * What is asserted is what the front-end runtime and a screen reader depend on: that a site's
 * own attributes cannot displace CoreX's, that a control written by hand is tied to its label and
 * its error place, and that what a site passes in is escaped.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Block\FieldRenderer;
use Corex\Forms\Block\FormParts;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Validation\RuleRegistry;

beforeEach(function () {
    Functions\when('esc_attr')->alias(static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES));
    Functions\when('esc_html')->alias(static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES));
});

function leadFormParts(): FormParts
{
    $schema = (new SchemaResolver(new RuleRegistry()))->resolve([
        'phone' => ['type' => 'phone', 'label' => 'Phone', 'rules' => ['required'], 'help_text' => 'With its area code.'],
        'note'  => ['type' => 'textarea', 'label' => 'Note'],
    ]);

    return new FormParts(
        'lead',
        ['class' => 'corex-form', 'novalidate' => 'novalidate', 'data-corex-endpoint' => 'https://example.test/wp-json/corex/v1/forms/lead'],
        $schema,
        '<input type="text" name="corex_hp" class="corex-form__hp" />',
        'Request a call',
        new FieldRenderer(),
    );
}

it('adds a class of the site to the one the runtime binds, and lets nothing replace what CoreX sets', function () {
    $attributes = leadFormParts()->attributes([
        'class'               => 'lead-card',
        'id'                  => 'lead',
        'data-corex-endpoint' => 'https://elsewhere.test/steal',
    ]);

    expect($attributes)->toBe(
        'class="corex-form lead-card" novalidate data-corex-endpoint="https://example.test/wp-json/corex/v1/forms/lead" id="lead"',
    );
});

it('escapes what a site passes in', function () {
    expect(leadFormParts()->attributes(['data-note' => '"><script>']))
        ->toContain('data-note="&quot;&gt;&lt;script&gt;"');
});

it('ties a control written by hand to its label, its help and its error place', function () {
    $parts = leadFormParts();

    expect($parts->control('phone', ['class' => 'lead-card__input']))
        ->toBe('id="corex-lead-phone" name="phone" aria-describedby="corex-lead-phone-help corex-lead-phone-error" required aria-required="true" class="lead-card__input"')
        ->and($parts->label('phone'))->toContain('<label for="corex-lead-phone"')
        ->and($parts->error('phone'))->toBe('<span class="corex-form__error" id="corex-lead-phone-error" role="alert"></span>')
        ->and($parts->fieldAttributes('phone'))->toBe('data-corex-field="phone" data-corex-visibility="visible"');
});

it('does not mark an optional control required', function () {
    expect(leadFormParts()->control('note'))
        ->toBe('id="corex-lead-note" name="note" aria-describedby="corex-lead-note-error"');
});

it('keeps the button a submit button with the form\'s label, whatever a site adds', function () {
    expect(leadFormParts()->submit(['class' => 'lead-card__go', 'type' => 'button']))
        ->toBe('<button type="submit" class="corex-form__submit lead-card__go">Request a call</button>');
});

it('refuses to supply a part for a field the form does not have', function () {
    expect(fn () => leadFormParts()->control('email'))
        ->toThrow(InvalidArgumentException::class, 'The form "lead" has no field named "email".');
});

it('names the parts a form\'s own markup is missing', function (string $markup, bool $protected, array $missing) {
    expect(FormParts::missingFrom($markup, $protected))->toBe($missing);
})->with([
    'nothing missing' => [
        '<form data-corex-endpoint="x"><input class="corex-form__hp"><p class="corex-form__status"></p></form>', false, [],
    ],
    'no status place, no hidden fields' => [
        '<form data-corex-endpoint="x"></form>', false, ['hidden()', 'status()'],
    ],
    'a bare form' => [
        '<form><input class="corex-form__hp"><p class="corex-form__status"></p></form>', false, ['attributes()'],
    ],
    'a protected form whose hidden fields were written by hand, without the token' => [
        '<form data-corex-endpoint="x"><input class="corex-form__hp"><p class="corex-form__status"></p></form>', true, ['hidden()'],
    ],
]);
