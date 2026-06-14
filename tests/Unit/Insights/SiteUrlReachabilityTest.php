<?php

/**
 * Unit tests for local/private URL detection (spec 044: US3, FR-011).
 *
 * @package Corex\Tests\Unit\Insights
 */

declare(strict_types=1);

use Corex\Config\Insights\SiteUrlReachability;

beforeEach(function () {
    $this->reach = new SiteUrlReachability();
});

it('treats localhost, .local/.test hosts, and loopback as non-public', function () {
    expect($this->reach->isPublic('http://localhost/'))->toBeFalse()
        ->and($this->reach->isPublic('http://corex.local'))->toBeFalse()
        ->and($this->reach->isPublic('http://corex.test'))->toBeFalse()
        ->and($this->reach->isPublic('http://127.0.0.1'))->toBeFalse();
});

it('treats private IPv4 ranges as non-public', function () {
    expect($this->reach->isPublic('http://192.168.1.10'))->toBeFalse()
        ->and($this->reach->isPublic('http://10.0.0.5'))->toBeFalse()
        ->and($this->reach->isPublic('http://172.16.0.1'))->toBeFalse();
});

it('treats a public host or IP as public', function () {
    expect($this->reach->isPublic('https://example.com/'))->toBeTrue()
        ->and($this->reach->isPublic('http://8.8.8.8'))->toBeTrue();
});

it('treats an empty or hostless URL as non-public', function () {
    expect($this->reach->isPublic(''))->toBeFalse()
        ->and($this->reach->isPublic('not a url'))->toBeFalse();
});
