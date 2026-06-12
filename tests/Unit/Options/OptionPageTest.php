<?php

/**
 * Unit tests for the declarative option page (spec 039: FR-001/002). It is a `FieldSections` so
 * the existing SettingsForm renders it with no duplicated form code.
 *
 * @package Corex\Tests\Unit\Options
 */

declare(strict_types=1);

use Corex\Config\Options\OptionPage;
use Corex\Config\Settings\FieldSections;

function page(): OptionPage
{
    return new OptionPage(
        slug: 'billing',
        title: 'Billing',
        menuLabel: 'Billing',
        capability: 'manage_options',
        parent: 'corex-settings',
        fields: [
            ['key' => 'billing.tax_id', 'label' => 'Tax ID', 'type' => 'text'],
            ['key' => 'billing.invoice_logo', 'label' => 'Invoice logo', 'type' => 'media'],
        ],
    );
}

it('exposes its menu metadata', function () {
    $p = page();

    expect($p->slug())->toBe('billing')
        ->and($p->title())->toBe('Billing')
        ->and($p->menuLabel())->toBe('Billing')
        ->and($p->capability())->toBe('manage_options')
        ->and($p->parent())->toBe('corex-settings');
});

it('is a FieldSections: one section built from its fields', function () {
    $p = page();

    expect($p)->toBeInstanceOf(FieldSections::class);

    $sections = $p->sections();
    expect($sections)->toHaveKey('billing')
        ->and($sections['billing']['title'])->toBe('Billing')
        ->and($sections['billing']['fields'])->toHaveKey('billing.tax_id')
        ->and($sections['billing']['fields']['billing.tax_id'])->toBe(['label' => 'Tax ID', 'type' => 'text'])
        ->and($sections['billing']['fields']['billing.invoice_logo']['type'])->toBe('media');
});

it('lists every field key', function () {
    expect(page()->keys())->toBe(['billing.tax_id', 'billing.invoice_logo']);
});

it('preserves select options in the field definition', function () {
    $p = new OptionPage('p', 'P', 'P', 'manage_options', '', [
        ['key' => 'p.mode', 'label' => 'Mode', 'type' => 'select', 'options' => ['a' => 'A', 'b' => 'B']],
    ]);

    expect($p->sections()['p']['fields']['p.mode']['options'])->toBe(['a' => 'A', 'b' => 'B']);
});
