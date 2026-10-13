<?php

/**
 * Unit tests for the one change made to WordPress's password-reset email: the address that
 * carries the key. Everything else in the email is WordPress's, or another plugin's.
 *
 * @package Corex\Tests\Unit\Profile
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Profile\Reset\ResetEmailLink;
use Corex\Profile\Reset\ResetLink;

const RESET_PAGE_ADDRESS = 'https://example.test/account/';

beforeEach(function () {
    Functions\when('get_permalink')->justReturn(RESET_PAGE_ADDRESS);
    Functions\when('add_query_arg')->alias(
        // As WordPress does it: the values are written as they are handed over.
        static fn (array $asked, string $address): string => $address . '?' . implode('&', array_map(
            static fn (string $name, string $value): string => $name . '=' . $value,
            array_keys($asked),
            $asked,
        )),
    );

    $this->email = new ResetEmailLink(new ResetLink());
});

it('replaces the address that carries the key and leaves the rest of the email', function (string $written) {
    $message = "To reset your password, visit the following address:\r\n\r\n" . $written . "\r\n\r\nSent from 127.0.0.1.\r\n";

    $changed = $this->email->toPage(12, $message, 'k3y', 'sal ma@example.test');

    $expected = RESET_PAGE_ADDRESS . '?corex_reset_login=sal%20ma%40example.test&corex_reset_key=k3y';

    expect($changed)->toContain($expected)
        ->and($changed)->not->toContain('wp-login.php')
        ->and($changed)->not->toContain('staff-door')
        ->and(str_replace($expected, 'ADDRESS', $changed))
            ->toBe(str_replace(resetAddressWritten($written), 'ADDRESS', $message));
})->with([
    'WordPress\'s own'               => ['https://example.test/wp-login.php?login=sal%20ma%40example.test&key=k3y&action=rp&wp_lang=en_US'],
    'the hidden login\'s'            => ['https://example.test/staff-door/?login=sal%20ma%40example.test&key=k3y&action=rp'],
    'one between angle brackets'     => ['<https://example.test/wp-login.php?action=rp&key=k3y&login=salma>'],
    'one a plugin wrote as a link'   => ['<a href="https://example.test/wp-login.php?action=rp&key=k3y&login=salma">Reset</a>'],
]);

it('leaves an email that carries no address with the key as it is', function () {
    // A longer key that begins with this one is another key.
    $message = "Another plugin wrote this email.\r\nhttps://example.test/somewhere/?key=k3yAndMore\r\n";

    expect($this->email->toPage(12, $message, 'k3y', 'salma'))->toBe($message);
});

it('reads a key with characters a pattern would act on as the text it is', function () {
    $message = 'https://example.test/wp-login.php?key=a.c$1&login=salma and https://example.test/wp-login.php?key=abc$1';

    expect($this->email->toPage(12, $message, 'a.c$1', 'salma'))
        ->toBe(RESET_PAGE_ADDRESS . '?corex_reset_login=salma&corex_reset_key=a.c%241 and https://example.test/wp-login.php?key=abc$1');
});

/**
 * The address inside what a dataset wrote, without the markup around it.
 */
function resetAddressWritten(string $written): string
{
    preg_match('~https://[^\s<>"]+~', $written, $found);

    return $found[0];
}
