<?php

/**
 * Unit tests for the per-type field renderer: every input type, choice groups, label
 * modes, column width, custom class/attributes, and the attribute safety filter.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Block\FieldRenderer;
use Corex\Forms\Schema\FieldSchema;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Validation\RuleRegistry;

function renderField(string $name, array $definition): string
{
    Functions\when('esc_html')->alias(static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES));
    Functions\when('esc_attr')->alias(static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES));

    $schema = (new SchemaResolver(new RuleRegistry()))->resolve([$name => $definition]);

    return (new FieldRenderer())->render('contact', $schema[$name]);
}

it('renders text-like inputs with the right type and accessible wiring', function () {
    expect(renderField('phone', ['type' => 'tel', 'label' => 'Phone']))
        ->toContain('type="tel"')
        ->toContain('id="corex-contact-phone"')
        ->toContain('aria-describedby="corex-contact-phone-error"')
        ->toContain('<label for="corex-contact-phone"');

    expect(renderField('age', ['type' => 'number']))->toContain('type="number"');
    expect(renderField('when', ['type' => 'date']))->toContain('type="date"');
    expect(renderField('cv', ['type' => 'file']))->toContain('type="file"');
    // Unknown type falls back to text.
    expect(renderField('x', ['type' => 'nonsense']))->toContain('type="text"');
});

it('renders a textarea and a select with options', function () {
    expect(renderField('note', ['type' => 'textarea']))->toContain('<textarea');

    $select = renderField('topic', [
        'type'    => 'select',
        'options' => ['sales' => 'Sales', 'support' => 'Support'],
    ]);

    expect($select)
        ->toContain('<select')
        ->toContain('<option value="sales">Sales</option>')
        ->toContain('<option value="support">Support</option>');

    expect(renderField('topics', [
        'type' => 'multi-select',
        'options' => ['news' => 'News'],
        'default_value' => ['news'],
    ]))
        ->toContain('<select')
        ->toContain('name="topics[]"')
        ->toContain(' multiple')
        ->toContain('<option value="news" selected>News</option>');
});

it('renders a radio group as a fieldset with a legend and one input per option', function () {
    $html = renderField('plan', [
        'type'    => 'radio',
        'label'   => 'Plan',
        'options' => ['free' => 'Free', 'pro' => 'Pro'],
        'rules'   => ['required'],
    ]);

    expect($html)
        ->toContain('<fieldset')
        ->toContain('<legend')
        ->toContain('type="radio"')
        ->toContain('value="free"')
        ->toContain('value="pro"')
        ->toContain('aria-required="true"');
});

it('renders a checkbox group whose inputs submit an array', function () {
    $html = renderField('topics', [
        'type'    => 'checkbox-group',
        'options' => ['a' => 'A', 'b' => 'B'],
    ]);

    expect($html)
        ->toContain('<fieldset')
        ->toContain('type="checkbox"')
        ->toContain('name="topics[]"');
});

it('renders a single checkbox and a toggle', function () {
    expect(renderField('agree', ['type' => 'checkbox', 'rules' => ['required']]))
        ->toContain('type="checkbox"')
        ->toContain('corex-form__checkbox')
        ->toContain('value="1"');

    expect(renderField('notify', ['type' => 'toggle']))->toContain('corex-form__toggle');
});

it('applies label modes, column width, and a custom control class', function () {
    $html = renderField('email', [
        'type'       => 'email',
        'label_mode' => 'hidden',
        'width'      => 'half',
        'class'      => 'is-brand',
    ]);

    expect($html)
        ->toContain('corex-form__label--hidden')
        ->toContain('corex-form__field--half')
        ->toContain('corex-form__input is-brand');
});

it('emits whitelisted extra attributes but drops reserved and event-handler ones', function () {
    $html = renderField('email', [
        'type'  => 'email',
        'attrs' => [
            'placeholder' => 'you@example.com',
            'autocomplete' => 'email',
            'onfocus'     => 'steal()',   // event handler — must be dropped
            'name'        => 'evil',      // reserved — must be dropped
        ],
    ]);

    expect($html)
        ->toContain('placeholder="you@example.com"')
        ->toContain('autocomplete="email"')
        ->not->toContain('onfocus')
        ->not->toContain('name="evil"');
});

/**
 * `accept` is derived from the field's own `mime:` rule, so the file picker and the validator
 * cannot disagree (spec 081).
 *
 * Driven through `renderField()` — and therefore through `SchemaResolver` — because that is where
 * the bug was. The first implementation matched `str_starts_with($rule, 'mime:')` on the declared
 * *string*, and the resolver had already parsed it into `['rule' => 'mime', 'params' => [...]]`, so
 * it matched nothing. Silently: a picker with no filter still works, so there was no symptom.
 * Building a `FieldSchema` by hand in the test would have reproduced my mistake, not the product's.
 */
it('derives the accept attribute from the resolved mime rule', function () {
    $html = renderField('cv', [
        'type'  => 'file',
        'label' => 'Your CV',
        'rules' => ['required', 'mime:application/pdf,image/png'],
    ]);

    expect($html)->toContain('accept="application/pdf,image/png"')
        ->and($html)->toContain('type="file"');
});

it('omits accept when the field declares no mime rule', function () {
    expect(renderField('cv', ['type' => 'file', 'rules' => ['required']]))
        ->not->toContain('accept=');
});
