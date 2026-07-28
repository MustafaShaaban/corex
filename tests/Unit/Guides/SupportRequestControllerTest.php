<?php

/**
 * Unit tests for the Guides support submission (spec 087, FR-005 / FR-007 / FR-008 / FR-009).
 *
 * `decide()` is under test rather than `handle()`, for the reason the class documents: `handle()`
 * ends in `exit`, which cannot be caught, so a test driving it would take the runner down and every
 * decision would go unproven. Here each decision is a return value.
 *
 * Nothing CoreX is doubled — the real guard, the real settings reader, the real mailer and the real
 * flash all run, with only WordPress stubbed. A double of the guard would have proved that a fake
 * gate can be told to say no; this proves the gate the screen actually posts through says no when
 * the nonce is wrong.
 *
 * @package Corex\Tests\Unit\Guides
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Guides\Support\SupportFlash;
use Corex\Guides\Support\SupportMailer;
use Corex\Guides\Support\SupportRequestController;
use Corex\Guides\Support\SupportSettings;
use Corex\Security\Admin\AdminGuard;
use Corex\Support\Config\ConfigInterface;

/** @param array<string,mixed> $stored */
function supportSettings(array $stored = []): SupportSettings
{
    return new SupportSettings(new class ($stored) implements ConfigInterface {
        /** @param array<string,mixed> $stored */
        public function __construct(private readonly array $stored)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->stored[$key] ?? $default;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->stored);
        }
    });
}

/**
 * Drive one submission and report what left the building.
 *
 * @param array<string,string> $post
 * @param array<string,mixed>  $settings
 *
 * @return array{destination:?string,mail:list<array<int,mixed>>,flash:array<string,mixed>}
 */
function submitSupport(array $post, array $settings = [], bool $validNonce = true, bool $throttled = false): array
{
    $mail = [];
    $transients = $throttled ? ['corex_guides_support_sent_7' => 1] : [];

    Functions\when('wp_verify_nonce')->justReturn($validNonce ? 1 : false);
    Functions\when('wp_mail')->alias(static function (...$args) use (&$mail): bool {
        $mail[] = $args;

        return true;
    });
    // A closure with a by-reference `use`, not an arrow function: an arrow function captures by
    // value, so it would answer from the transient table as it looked before the flash was stored.
    Functions\when('get_transient')->alias(
        static function (string $key) use (&$transients): mixed {
            return $transients[$key] ?? false;
        },
    );
    Functions\when('set_transient')->alias(
        static function (string $key, mixed $value) use (&$transients): bool {
            $transients[$key] = $value;

            return true;
        },
    );
    Functions\when('delete_transient')->alias(
        static function (string $key) use (&$transients): bool {
            unset($transients[$key]);

            return true;
        },
    );

    $_POST = $post + [SupportRequestController::NONCE => 'nonce-value'];

    $flash = new SupportFlash();
    $controller = new SupportRequestController(
        new AdminGuard(),
        supportSettings($settings),
        new SupportMailer(),
        $flash,
    );

    $destination = $controller->decide();

    return [
        'destination' => $destination,
        'mail'        => $mail,
        'flash'       => $flash->take(7) ?? [],
    ];
}

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('is_email')->alias(
        static fn (string $value): string|false => filter_var($value, FILTER_VALIDATE_EMAIL) ?: false,
    );
    Functions\when('admin_url')->alias(
        static fn (string $path = ''): string => 'https://example.test/wp-admin/' . $path,
    );
    Functions\when('home_url')->justReturn('https://example.test');
    Functions\when('get_bloginfo')->justReturn('Example Site');
    Functions\when('wp_specialchars_decode')->returnArg();
    Functions\when('wp_unslash')->returnArg();
    Functions\when('current_user_can')->justReturn(true);
    Functions\when('sanitize_text_field')->alias(
        static fn (string $value): string => trim(strip_tags($value)),
    );
    Functions\when('sanitize_key')->alias(
        static fn (string $value): string => strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', $value)),
    );
    Functions\when('sanitize_textarea_field')->alias(
        static fn (string $value): string => trim(strip_tags($value)),
    );
    Functions\when('sanitize_email')->alias(
        static fn (string $value): string => filter_var(trim($value), FILTER_VALIDATE_EMAIL) ?: '',
    );
    Functions\when('get_current_user_id')->justReturn(7);
    Functions\when('wp_get_current_user')->justReturn((object) [
        'display_name' => 'Reader',
        'user_email'   => 'reader@example.test',
    ]);
    Functions\when('add_action')->justReturn(true);
    Functions\when('remove_action')->justReturn(true);

    $_POST = [];
});

afterEach(function () {
    $_POST = [];
});

/**
 * The refusal that matters. A missing or stale nonce must stop the handler before it reads a single
 * field — not send the message and then complain.
 */
it('refuses outright when the nonce does not verify', function () {
    $result = submitSupport(['corex_guides_message' => 'Anything'], validNonce: false);

    expect($result['destination'])->toBeNull()
        ->and($result['mail'])->toBeEmpty()
        ->and($result['flash'])->toBeEmpty();
});

it('refuses outright when the actor cannot even read the admin', function () {
    Functions\when('current_user_can')->justReturn(false);

    $result = submitSupport(['corex_guides_message' => 'Anything']);

    expect($result['destination'])->toBeNull()
        ->and($result['mail'])->toBeEmpty();
});

it('sends the message to the configured address and reports that it was sent', function () {
    $result = submitSupport([
        'corex_guides_category' => 'suggestion',
        'corex_guides_message'  => 'The retention guide stops before the prune step.',
        'corex_guides_email'    => 'reader@example.test',
    ], settings: [SupportSettings::EMAIL_KEY => 'ops@example.test']);

    expect($result['destination'])->toContain('page=corex-guides')
        ->and($result['mail'])->toHaveCount(1)
        ->and($result['mail'][0][0])->toBe('ops@example.test')
        ->and($result['mail'][0][1])->toContain('A suggestion')
        ->and($result['mail'][0][2])->toContain('The retention guide stops before the prune step.')
        ->and($result['mail'][0][3])->toBe(['Reply-To: reader@example.test'])
        ->and($result['flash']['outcome'])->toBe(SupportFlash::SENT);
});

it('falls back to the shipped address when the site has saved none', function () {
    $result = submitSupport(['corex_guides_message' => 'Hello']);

    expect($result['mail'][0][0])->toBe('Mustafashaaban22@gmail.com');
});

/**
 * A bot gets the confirmation a person gets. Telling the two apart is the one thing that would make
 * the trap worth defeating.
 */
it('silently discards a submission that filled the honeypot', function () {
    $result = submitSupport([
        'corex_guides_message' => 'Buy things',
        'corex_guides_website' => 'https://spam.example',
    ]);

    expect($result['mail'])->toBeEmpty()
        ->and($result['flash']['outcome'])->toBe(SupportFlash::SENT);
});

it('does not send an empty message, and gives back what was typed', function () {
    $result = submitSupport(['corex_guides_message' => "   \n  "]);

    expect($result['mail'])->toBeEmpty()
        ->and($result['flash']['outcome'])->toBe(SupportFlash::EMPTY_MESSAGE);
});

/**
 * Server-side, because a disabled button covers neither a second tab, a double-click, nor a
 * replayed POST.
 */
it('throttles a second message from the same person', function () {
    $result = submitSupport(['corex_guides_message' => 'Again'], throttled: true);

    expect($result['mail'])->toBeEmpty()
        ->and($result['flash']['outcome'])->toBe(SupportFlash::THROTTLED)
        ->and($result['flash']['message'])->toBe('Again');
});

it('does not pretend to send when the site has no support address', function () {
    $result = submitSupport(
        ['corex_guides_message' => 'Hello'],
        settings: [SupportSettings::EMAIL_KEY => ''],
    );

    expect($result['mail'])->toBeEmpty()
        ->and($result['flash']['outcome'])->toBe(SupportFlash::UNAVAILABLE);
});

it('does not send when the support form has been switched off', function () {
    $result = submitSupport(
        ['corex_guides_message' => 'Hello'],
        settings: [SupportSettings::ENABLED_KEY => ''],
    );

    expect($result['mail'])->toBeEmpty()
        ->and($result['flash']['outcome'])->toBe(SupportFlash::UNAVAILABLE);
});

/**
 * The category shapes the subject line, so an unrecognised one must land on a known value rather
 * than reach the mail header as whatever was posted.
 */
it('falls back to a known category rather than trusting the posted one', function () {
    $result = submitSupport([
        'corex_guides_category' => '../../etc/passwd',
        'corex_guides_message'  => 'Hello',
    ]);

    expect($result['mail'][0][1])->toContain('A question')
        ->and($result['mail'][0][1])->not->toContain('passwd');
});

it('drops a reply address that is not one', function () {
    $result = submitSupport([
        'corex_guides_message' => 'Hello',
        'corex_guides_email'   => 'not an address',
    ]);

    expect($result['mail'][0][3])->toBe([]);
});

it('carries the context that makes the message answerable', function () {
    $result = submitSupport([
        'corex_guides_message' => 'Where is the prune step?',
        'corex_guides_email'   => 'reader@example.test',
    ]);

    expect($result['mail'][0][2])
        ->toContain('Reader')
        ->toContain('reader@example.test')
        ->toContain('https://example.test')
        ->toContain('Where is the prune step?');
});
