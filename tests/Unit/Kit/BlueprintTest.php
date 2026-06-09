<?php

/**
 * Unit tests for the kit Blueprint engine (spec 010 US3: FR-004, SC-004).
 *
 * @package Corex\Tests\Unit\Kit
 */

declare(strict_types=1);

use Corex\Kit\BlueprintRegistry;
use Corex\Kit\Company\CompanyBlueprint;

it('registers and finds the company blueprint by name', function () {
    $registry = new BlueprintRegistry();
    $registry->register(new CompanyBlueprint());

    expect($registry->find('company'))->toBeInstanceOf(CompanyBlueprint::class)
        ->and($registry->find('missing'))->toBeNull()
        ->and($registry->all())->toHaveCount(1);
});

it('describes the templates, parts, patterns, and modules the company kit provides', function () {
    $kit = new CompanyBlueprint();

    expect($kit->requiredModules())->toContain('corex-ui')
        ->and($kit->recommendedModules())->toContain('corex-forms', 'corex-email')
        ->and($kit->templates())->toContain('front-page', 'page', 'single', 'archive', 'search', '404')
        ->and($kit->parts())->toContain('header', 'footer')
        ->and($kit->patterns())->toContain('corex/hero', 'corex/contact');
});
