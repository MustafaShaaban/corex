<?php

/**
 * A sender's name as it may stand in a From header (spec 106, story 5).
 *
 * One rule in one place: the reply's name from the settings and the site's general name both
 * pass through it, in the gateway and again in the driver.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Corex\Email\Message\SenderName;

it('leaves a name a name, and takes out what would be read as something else', function (string $written, string $name) {
    expect(SenderName::of($written))->toBe($name);
})->with([
    'a name' => ['Acme Sales', 'Acme Sales'],
    'a name in another alphabet' => ['مبيعات أكمي', 'مبيعات أكمي'],
    // Kept as written. Cleaned as a form field is, the first came out "Tom & Jerry &lt;3" and
    // the second lost its "%ae".
    'an ampersand and a heart' => ['Tom & Jerry <3', 'Tom & Jerry 3'],
    'a percentage' => ['Save 50%ae today', 'Save 50%ae today'],
    // WordPress takes a From header apart at the first angle bracket.
    'something shaped like an address' => ['Acme <boss@example.org>', 'Acme boss@example.org'],
    'quotes' => ['Acme "Kitchens"', 'Acme Kitchens'],
    // A header ends at a line's end.
    'a second line' => ["Acme\r\nBcc: x@example.com", 'Acme Bcc: x@example.com'],
    'a tab and a null' => ["Acme\t\0Sales", 'Acme Sales'],
    // A mail program decodes this as words: here, as "Boss <boss@example.org>".
    'words written in the code a mail program decodes' => ['=?utf-8?B?Qm9zcyA8Ym9zc0BleGFtcGxlLm9yZz4=?=', 'utf-8?B?Qm9zcyA8Ym9zc0BleGFtcGxlLm9yZz4='],
    'spaces around and between' => ['   Acme     Sales  ', 'Acme Sales'],
    'only spaces' => ["  \t ", ''],
    'nothing' => ['', ''],
]);

it('keeps a name to the length of a name', function () {
    $name = SenderName::of(str_repeat('Acme Sales ', 40));

    expect(mb_strlen($name))->toBeLessThanOrEqual(100)
        ->and($name)->toStartWith('Acme Sales Acme Sales')
        ->and($name)->not->toEndWith(' ');
});

it('answers text for bytes that are not text', function () {
    $name = SenderName::of("Acme \xE9 Sales");

    expect(mb_check_encoding($name, 'UTF-8'))->toBeTrue()
        ->and($name)->toStartWith('Acme')->toEndWith('Sales');
});
