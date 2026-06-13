<?php

/**
 * Unit tests for the pure blank-content predicate (spec 041: adoptable vs user-owned). No WordPress.
 *
 * @package Corex\Tests\Unit\Provisioning
 */

declare(strict_types=1);

use Corex\Provisioning\PageContent;

it('treats an empty string as blank', function () {
    expect((new PageContent())->isBlank(''))->toBeTrue();
});

it('treats whitespace-only content as blank', function () {
    expect((new PageContent())->isBlank("  \n\t  "))->toBeTrue();
});

it('treats a single empty paragraph block as blank', function () {
    $content = "<!-- wp:paragraph -->\n<p></p>\n<!-- /wp:paragraph -->";
    expect((new PageContent())->isBlank($content))->toBeTrue();
});

it('treats a bare empty paragraph as blank', function () {
    expect((new PageContent())->isBlank('<p></p>'))->toBeTrue();
});

it('treats real text as not blank', function () {
    expect((new PageContent())->isBlank('<p>Hello world</p>'))->toBeFalse();
});

it('treats a real corex block as not blank', function () {
    $content = '<!-- wp:corex/hero {"title":"Welcome"} /-->';
    expect((new PageContent())->isBlank($content))->toBeFalse();
});

it('treats a paragraph with text inside the block wrapper as not blank', function () {
    $content = "<!-- wp:paragraph -->\n<p>Real content</p>\n<!-- /wp:paragraph -->";
    expect((new PageContent())->isBlank($content))->toBeFalse();
});
