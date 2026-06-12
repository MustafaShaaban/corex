<?php

/**
 * Unit tests for the option-page registry (spec 039: FR-003).
 *
 * @package Corex\Tests\Unit\Options
 */

declare(strict_types=1);

use Corex\Config\Options\OptionPage;
use Corex\Config\Options\OptionPageRegistry;

function aPage(string $slug): OptionPage
{
    return new OptionPage($slug, ucfirst($slug), ucfirst($slug), 'manage_options', '', []);
}

it('registers, lists and finds pages by slug', function () {
    $registry = new OptionPageRegistry();
    $registry->register(aPage('billing'));
    $registry->register(aPage('shipping'));

    expect($registry->all())->toHaveCount(2)
        ->and($registry->find('billing'))->not->toBeNull()
        ->and($registry->find('billing')->title())->toBe('Billing')
        ->and($registry->find('nope'))->toBeNull();
});
