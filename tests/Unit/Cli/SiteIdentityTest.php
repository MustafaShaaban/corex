<?php

/**
 * Unit tests for the client-site identity deriver (spec 049: US1, FR-001).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Site\SiteIdentity;
use Corex\Cli\Support\InvalidNameException;

it('derives the full identity from a site name, distinct from Corex', function () {
    $id = SiteIdentity::from('Acme');

    expect($id->namespace)->toBe('AcmeSite')
        ->and($id->pluginSlug)->toBe('acme-site')
        ->and($id->themeSlug)->toBe('acme-theme')
        ->and($id->textDomain)->toBe('acme-site')
        ->and($id->restNamespace)->toBe('acme/v1')
        ->and($id->cssPrefix)->toBe('--acme-')
        ->and($id->optionPrefix)->toBe('acme_');
});

it('handles a multi-word name', function () {
    $id = SiteIdentity::from('Acme Corp');

    expect($id->namespace)->toBe('AcmeCorpSite')
        ->and($id->pluginSlug)->toBe('acme-corp-site')
        ->and($id->themeSlug)->toBe('acme-corp-theme')
        ->and($id->restNamespace)->toBe('acme-corp/v1');
});

it('is never equal to Corex\'s own identity', function () {
    $id = SiteIdentity::from('Acme');

    expect($id->namespace)->not->toBe('Corex')
        ->and($id->cssPrefix)->not->toBe('--corex-')
        ->and($id->restNamespace)->not->toBe('corex/v1')
        ->and($id->optionPrefix)->not->toBe('corex_');
});

it('refuses an empty name or one that collides with Corex', function () {
    expect(fn () => SiteIdentity::from(''))->toThrow(InvalidNameException::class)
        ->and(fn () => SiteIdentity::from('corex'))->toThrow(InvalidNameException::class)
        ->and(fn () => SiteIdentity::from('Corex'))->toThrow(InvalidNameException::class);
});
