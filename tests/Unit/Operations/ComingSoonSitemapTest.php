<?php

/**
 * Unit tests for the sitemap a site in Coming soon publishes (spec 101, FR-019): the home URL and
 * nothing else. No WordPress — the home URL is handed in.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Corex\Config\Operations\ComingSoonSitemap;

it('lists the home URL and no other', function () {
    $xml = (new ComingSoonSitemap())->xml('https://example.test/');

    $document = simplexml_load_string($xml);

    expect($document)->not->toBeFalse()
        ->and($document->getName())->toBe('urlset')
        ->and($document->getNamespaces())->toBe(['' => 'http://www.sitemaps.org/schemas/sitemap/0.9'])
        ->and(count($document->url))->toBe(1)
        ->and((string) $document->url[0]->loc)->toBe('https://example.test/');
});

it('opens with an XML declaration, so the document is not sniffed as something else', function () {
    expect((new ComingSoonSitemap())->xml('https://example.test/'))
        ->toStartWith('<?xml version="1.0" encoding="UTF-8"?>');
});

it('stays well-formed when the home URL holds characters XML reserves', function () {
    // A filtered home URL can carry a query string. An unescaped ampersand is not a cosmetic
    // fault in XML: the whole document fails to parse, and the crawler is told nothing.
    $home = 'https://example.test/?lang=ar&ref=<launch>';

    $document = simplexml_load_string((new ComingSoonSitemap())->xml($home));

    expect($document)->not->toBeFalse()
        ->and((string) $document->url[0]->loc)->toBe($home);
});
