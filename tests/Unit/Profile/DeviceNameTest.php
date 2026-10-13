<?php

/**
 * Unit tests for the name a session goes by in the account block (spec 068, FR-161g).
 *
 * A session's row was the browser's whole user agent string. What its owner knows it by is
 * the browser and the system, so that is what is read from the string, with no version: a
 * version changes every few weeks and a user agent no longer tells Windows 10 from 11.
 *
 * @package Corex\Tests\Unit\Profile
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Profile\Session\DeviceName;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('names the browser and the system a user agent was sent from', function (string $userAgent, string $name) {
    expect(DeviceName::fromUserAgent($userAgent))->toBe($name);
})->with([
    'Chrome on Windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        'Chrome on Windows',
    ],
    'Edge, which also says Chrome and Safari' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0',
        'Edge on Windows',
    ],
    'Opera, which also says Chrome and Safari' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 OPR/125.0.0.0',
        'Opera on Windows',
    ],
    'Firefox on Linux' => [
        'Mozilla/5.0 (X11; Linux x86_64; rv:143.0) Gecko/20100101 Firefox/143.0',
        'Firefox on Linux',
    ],
    'Safari on a Mac' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15',
        'Safari on macOS',
    ],
    'Safari on an iPhone, which says "like Mac OS X"' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        'Safari on iPhone',
    ],
    'Safari on an iPad' => [
        'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        'Safari on iPad',
    ],
    'Chrome on an iPhone' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/141.0.0.0 Mobile/15E148 Safari/604.1',
        'Chrome on iPhone',
    ],
    'Firefox on an iPhone' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/143.0 Mobile/15E148 Safari/605.1.15',
        'Firefox on iPhone',
    ],
    'Chrome on Android, which says Linux' => [
        'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
        'Chrome on Android',
    ],
    'Samsung Internet, which also says Chrome' => [
        'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/27.0 Chrome/125.0.0.0 Mobile Safari/537.36',
        'Samsung Internet on Android',
    ],
    'Chrome on a Chromebook' => [
        'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        'Chrome on ChromeOS',
    ],
]);

it('says so when a user agent names no browser it knows', function (string $userAgent, string $name) {
    expect(DeviceName::fromUserAgent($userAgent))->toBe($name);
})->with([
    'a command-line client'              => ['curl/8.9.1', 'Unknown browser'],
    'a session recorded with none'       => ['', 'Unknown browser'],
    'an app that names its system only'  => ['Dalvik/2.1.0 (Linux; U; Android 14; Pixel 8 Build/UQ1A)', 'Unknown browser on Android'],
]);

it('hands the name to a translator as a browser and a system, to order as the language does', function () {
    Functions\when('__')->alias(
        static fn (string $text): string => $text === '%1$s on %2$s' ? '%2$s: %1$s' : $text,
    );

    expect(DeviceName::fromUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:143.0) Gecko/20100101 Firefox/143.0'))
        ->toBe('Linux: Firefox');
});
