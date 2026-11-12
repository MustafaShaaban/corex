<?php

/**
 * What a login settings request asks to change (spec 124, FR-015, User Story 7 scenarios 2 to 4).
 *
 * The route saves only the fields a request sends, in the units the screen shows them (minutes for
 * the counting window and the lockout), and refuses a value outside its range with the range, or a
 * trusted proxy line that is not an address or range with that line. The ranges are
 * {@see LoginSettingsInput::RANGES}.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginSettingsInput;
use Corex\Config\Security\LoginProtection\LoginSettingsProblem;
use Corex\Config\Security\LoginProtection\LoginSettingsRefused;
use Corex\Config\Security\LoginProtection\LoginSlug;
use Corex\Config\Security\LoginProtection\LoginSwitches;

beforeEach(function () {
    Functions\when('sanitize_title')->alias(static function (string $title): string {
        $slug = strtolower($title);
        $slug = (string) preg_replace('/[^a-z0-9\-]+/', '-', $slug);

        return trim((string) preg_replace('/-+/', '-', $slug), '-');
    });

    // A site whose counting window was set to 30 seconds from the command line.
    $this->current = LoginProtectionSettings::of(
        new LoginSwitches(hideWpLogin: false, hideAdmin: true, limitAttempts: true),
        customSlug: 'team-entry',
        windowSeconds: 30,
    );
});

it('asks to change only the fields the request sends (FR-015, scenario 2)', function () {
    $input = LoginSettingsInput::read(['max_attempts' => 9], $this->current);

    expect($input->problems)->toBe([])
        ->and($input->changes)->toBe(['threshold' => 9])
        ->and($input->switches)->toBeNull();
});

it('refuses a request with no field it knows: there is nothing to save (FR-015)', function () {
    $input = LoginSettingsInput::read(['_wpnonce' => 'x', 'login_url' => 'https://example.test/'], $this->current);

    expect(array_keys($input->problems))->toBe(['request'])
        ->and($input->problems['request']->reason)->toBe(LoginSettingsProblem::REASON_NOTHING_TO_SAVE)
        ->and($input->changes)->toBe([]);
});

it('takes a value equal to the one in force as no change, and does not check it (FR-015)', function () {
    $input = LoginSettingsInput::read([
        'max_attempts'             => 5,
        'window_seconds'           => 30,
        'custom_slug'              => 'Team-Entry',
        'successful_login_logging' => true,
        'trusted_proxy_mode'       => false,
    ], $this->current);

    expect($input->problems)->toBe([])
        ->and($input->changes)->toBe([]);
});

it('turns the minutes and days the screen shows into the stored seconds and days (FR-015)', function () {
    $input = LoginSettingsInput::read([
        'window_minutes'           => 10,
        'lockout_minutes'          => 20,
        'retention_days'           => 45,
        'successful_login_logging' => false,
    ], $this->current);

    expect($input->problems)->toBe([])
        ->and($input->changes)->toBe([
            'window_seconds'           => 600,
            'lockout_seconds'          => 1200,
            'retain_days'              => 45,
            'successful_login_logging' => false,
        ]);
});

it('still takes the seconds the previous screen sends (FR-015, until slice 10)', function () {
    $input = LoginSettingsInput::read(['window_seconds' => 300, 'lockout_seconds' => 1200], $this->current);

    expect($input->problems)->toBe([])
        ->and($input->changes)->toBe(['window_seconds' => 300, 'lockout_seconds' => 1200]);
});

it('accepts every number at the edges of its range (FR-015)', function (string $field, int $value) {
    expect(LoginSettingsInput::read([$field => $value], $this->current)->problems)->toBe([]);
})->with(function () {
    foreach (LoginSettingsInput::RANGES as $field => [$min, $max]) {
        yield "{$field} {$min}" => [$field, $min];
        yield "{$field} {$max}" => [$field, $max];
    }
});

it('refuses a number outside its range and names the range (FR-015, scenario 3)', function (string $field, mixed $value) {
    $input = LoginSettingsInput::read([$field => $value, 'successful_login_logging' => true], $this->current);
    [$min, $max] = LoginSettingsInput::RANGES[$field];

    expect($input->problems)->toHaveKey($field)
        ->and($input->problems[$field]->reason)->toBe(LoginSettingsProblem::REASON_OUT_OF_RANGE)
        ->and($input->problems[$field]->min)->toBe($min)
        ->and($input->problems[$field]->max)->toBe($max);
})->with(function () {
    foreach (LoginSettingsInput::RANGES as $field => [$min, $max]) {
        yield "{$field} below" => [$field, $min - 1];
        yield "{$field} above" => [$field, $max + 1];
        yield "{$field} not whole" => [$field, $min + 0.5];
        yield "{$field} text" => [$field, 'five'];
    }
});

it('refuses a lockout of 0 minutes with its range (scenario 3)', function () {
    $problem = LoginSettingsInput::read(['lockout_minutes' => 0], $this->current)->problems['lockout_minutes'];

    expect([$problem->min, $problem->max])->toBe([1, 10080]);
});

it('takes a whole number sent as text, the way a form posts it', function () {
    expect(LoginSettingsInput::read(['max_attempts' => '8'], $this->current)->changes)->toBe(['threshold' => 8]);
});

it('refuses a switch that is neither on nor off', function (string $field) {
    $input = LoginSettingsInput::read([$field => 'perhaps'], $this->current);

    expect($input->problems[$field]->reason)->toBe(LoginSettingsProblem::REASON_NOT_ON_OR_OFF);
})->with(['hide_wp_login', 'hide_admin', 'limit_attempts', 'enabled', 'block_default_endpoints', 'trusted_proxy_mode', 'successful_login_logging']);

it('refuses an empty string as a switch value, rather than read it as off', function (string $field) {
    $input = LoginSettingsInput::read([$field => ''], $this->current);

    expect($input->problems[$field]->reason)->toBe(LoginSettingsProblem::REASON_NOT_ON_OR_OFF)
        ->and($input->changes)->toBe([]);
})->with(['hide_wp_login', 'hide_admin', 'limit_attempts', 'enabled', 'block_default_endpoints', 'trusted_proxy_mode', 'successful_login_logging']);

it('changes one switch and keeps the other two as they are (FR-013, FR-015)', function () {
    $switches = LoginSettingsInput::read(['hide_admin' => false], $this->current)->switches;

    expect($switches)->toEqual(new LoginSwitches(hideWpLogin: false, hideAdmin: false, limitAttempts: true));
});

it('reads the previous screen\'s two keys in their old meaning (FR-014)', function () {
    $switches = LoginSettingsInput::read(['enabled' => true, 'block_default_endpoints' => true], $this->current)->switches;

    expect($switches)->toEqual(new LoginSwitches(true, true, true));
});

it('refuses the switches beside an old key that says otherwise, keyed by the old key sent', function () {
    $input = LoginSettingsInput::read(['hide_admin' => true, 'enabled' => false], $this->current);

    expect(array_keys($input->problems))->toBe(['enabled'])
        ->and($input->problems['enabled']->reason)->toBe(LoginSettingsProblem::REASON_TWO_SHAPES)
        ->and($input->switches)->toBeNull();

    $input = LoginSettingsInput::read(['hide_wp_login' => false, 'hide_admin' => false, 'block_default_endpoints' => true], $this->current);

    expect(array_keys($input->problems))->toBe(['block_default_endpoints']);
});

it('takes the switches beside the old keys they would write, as the route answers them (FR-014)', function () {
    $input = LoginSettingsInput::read([
        'hide_wp_login'           => true,
        'hide_admin'              => true,
        'limit_attempts'          => true,
        'enabled'                 => true,
        'block_default_endpoints' => true,
    ], $this->current);

    expect($input->problems)->toBe([])
        ->and($input->switches)->toEqual(new LoginSwitches(true, true, true));
});

it('takes the switches and old keys in force, posted back, as no change (FR-014, FR-015)', function () {
    $input = LoginSettingsInput::read([
        'hide_wp_login'           => false,
        'hide_admin'              => true,
        'limit_attempts'          => true,
        'enabled'                 => true,
        'block_default_endpoints' => false,
    ], $this->current);

    expect($input->problems)->toBe([])
        ->and($input->switches)->toBeNull();
});

it('changes no switch when the previous screen sends the old pair as it is in force (FR-014)', function () {
    // wp-login.php hidden, limits off: a combination the previous screen cannot show.
    $current = LoginProtectionSettings::of(new LoginSwitches(hideWpLogin: true, hideAdmin: false, limitAttempts: false), customSlug: 'team-entry');

    $input = LoginSettingsInput::read([
        'enabled'                 => $current->enabled,
        'block_default_endpoints' => $current->blockDefaultEndpoints,
        'max_attempts'            => 8,
    ], $current);

    expect($input->problems)->toBe([])
        ->and($input->switches)->toBeNull()
        ->and($input->changes)->toBe(['threshold' => 8]);
});

it('refuses a length sent in minutes and in seconds that disagree', function () {
    $input = LoginSettingsInput::read(['window_minutes' => 5, 'window_seconds' => 120], $this->current);

    expect(array_keys($input->problems))->toBe(['window_minutes'])
        ->and($input->problems['window_minutes']->reason)->toBe(LoginSettingsProblem::REASON_TWO_UNITS);
});

it('takes minutes and seconds that agree, as the route answers them', function () {
    $input = LoginSettingsInput::read(['window_minutes' => 5, 'window_seconds' => 300], $this->current);

    expect($input->problems)->toBe([])
        ->and($input->changes)->toBe(['window_seconds' => 300]);
});

it('takes no minutes beside the seconds of a length that is not a whole minute (FR-015)', function () {
    $input = LoginSettingsInput::read(['window_minutes' => null, 'window_seconds' => 30, 'max_attempts' => 6], $this->current);

    expect($input->problems)->toBe([])
        ->and($input->changes)->toBe(['threshold' => 6]);
});

it('saves a trusted proxy range, as a list or one per line (scenario 4)', function (mixed $sent) {
    $input = LoginSettingsInput::read(['trusted_proxies' => $sent], $this->current);

    expect($input->problems)->toBe([])
        ->and($input->changes)->toBe([
            'trusted_proxy_ranges' => ['203.0.113.0/24', '2001:db8::/32', '198.51.100.7'],
            'trusted_proxy_mode'   => true,
        ]);
})->with([
    'list'          => [['203.0.113.0/24', ' 2001:db8::/32 ', '', '198.51.100.7']],
    'lines'         => ["203.0.113.0/24\n2001:db8::/32\r\n\n198.51.100.7\n"],
]);

it('trusts nobody when the list is emptied', function () {
    $current = LoginProtectionSettings::of(new LoginSwitches(false, false, true), trustedProxyMode: true, trustedProxyRanges: ['203.0.113.0/24']);

    expect(LoginSettingsInput::read(['trusted_proxies' => ''], $current)->changes)
        ->toBe(['trusted_proxy_ranges' => [], 'trusted_proxy_mode' => false]);
});

it('stores each proxy line in one spelling (FR-015)', function () {
    expect(LoginSettingsInput::read(['trusted_proxies' => "2001:DB8::1\n2001:0db8:0000:0000:0000:0000:0000:0002/128"], $this->current)->changes)
        ->toBe(['trusted_proxy_ranges' => ['2001:db8::1', '2001:db8::2/128'], 'trusted_proxy_mode' => true]);
});

it('takes the stored list posted back as no change, even one the rules now refuse (FR-015)', function (array $stored, mixed $sent) {
    $current = LoginProtectionSettings::of(new LoginSwitches(false, false, true), trustedProxyMode: true, trustedProxyRanges: $stored);

    $input = LoginSettingsInput::read(['trusted_proxies' => $sent, 'max_attempts' => 6], $current);

    expect($input->problems)->toBe([])
        ->and($input->changes)->toBe(['threshold' => 6]);
})->with([
    'a /0 range'                => [['0.0.0.0/0'], ['0.0.0.0/0']],
    '101 lines'                 => [array_map(static fn (int $i): string => "10.0.0.{$i}", range(1, 101)), array_map(static fn (int $i): string => "10.0.0.{$i}", range(1, 101))],
    'another spelling, as text' => [['2001:DB8::1'], "2001:db8::1\n"],
]);

it('refuses a proxy line that is not an IPv4 or IPv6 address or range, naming the line (scenario 3)', function () {
    $problem = LoginSettingsInput::read(
        ['trusted_proxies' => "203.0.113.0/24\n10.0.0.0/33\nnot-an-address"],
        $this->current,
    )->problems['trusted_proxies'];

    expect($problem->reason)->toBe(LoginSettingsProblem::REASON_NOT_A_PROXY)
        ->and($problem->line)->toBe(2)
        ->and($problem->value)->toBe('10.0.0.0/33')
        ->and($problem->lines)->toBe([2 => '10.0.0.0/33', 3 => 'not-an-address']);
});

it('tells an address or range from anything else', function (string $line, bool $valid) {
    $problems = LoginSettingsInput::read(['trusted_proxies' => [$line]], $this->current)->problems;

    expect($problems === [])->toBe($valid);
})->with([
    'IPv4'                => ['203.0.113.7', true],
    'IPv4 range'          => ['203.0.113.0/24', true],
    'IPv4 /32'            => ['203.0.113.7/32', true],
    'IPv4 /33'            => ['10.0.0.0/33', false],
    'IPv6'                => ['2001:db8::1', true],
    'IPv6 /128'           => ['2001:db8::/128', true],
    'IPv6 /129'           => ['2001:db8::/129', false],
    'negative prefix'     => ['10.0.0.0/-1', false],
    'empty prefix'        => ['10.0.0.0/', false],
    'host name'           => ['proxy.example.test', false],
    'two slashes'         => ['10.0.0.0/8/8', false],
    'markup'              => ['<b>10.0.0.1</b>', false],
    'IPv4 /0'             => ['0.0.0.0/0', false],
    'IPv6 /0'             => ['::/0', false],
    'IPv4 /1'             => ['0.0.0.0/1', true],
]);

it('refuses a range that trusts every address with a reason of its own, naming the line (FR-015)', function (string $range) {
    $problem = LoginSettingsInput::read(['trusted_proxies' => "203.0.113.0/24\n{$range}"], $this->current)->problems['trusted_proxies'];

    expect($problem->reason)->toBe(LoginSettingsProblem::REASON_TRUSTS_EVERY_ADDRESS)
        ->and($problem->line)->toBe(2)
        ->and($problem->value)->toBe($range);
})->with(['0.0.0.0/0', '::/0']);

it('refuses more trusted proxy lines than it keeps', function () {
    $lines = array_map(static fn (int $i): string => '10.0.' . intdiv($i, 256) . '.' . ($i % 256), range(1, LoginSettingsInput::MAX_PROXIES + 1));

    $problem = LoginSettingsInput::read(['trusted_proxies' => $lines], $this->current)->problems['trusted_proxies'];

    expect($problem->reason)->toBe(LoginSettingsProblem::REASON_TOO_MANY_PROXIES)
        ->and($problem->max)->toBe(LoginSettingsInput::MAX_PROXIES);
});

it('takes the trusted proxy switch as sent', function () {
    // Sent off, as it is in force: the list changes and the switch stays off.
    expect(LoginSettingsInput::read(['trusted_proxies' => ['203.0.113.0/24'], 'trusted_proxy_mode' => false], $this->current)->changes)
        ->toBe(['trusted_proxy_ranges' => ['203.0.113.0/24']]);
});

it('repeats at most 64 bytes of a refused line, never half a character', function () {
    // A three-byte character: byte 64 falls inside the 22nd, so 21 are kept.
    $line = str_repeat("\u{20ac}", 30);
    $problem = LoginSettingsInput::read(['trusted_proxies' => [$line]], $this->current)->problems['trusted_proxies'];

    expect(strlen($problem->value))->toBe(63)
        ->and(mb_check_encoding($problem->value, 'UTF-8'))->toBeTrue();
});

it('normalises a login address the way it is stored', function () {
    expect(LoginSettingsInput::read(['custom_slug' => ' Team Door '], $this->current)->changes)
        ->toBe(['custom_slug' => 'team-door']);
});

it('refuses an unusable login address with its reason', function () {
    $problem = LoginSettingsInput::read(['custom_slug' => 'wp-admin'], $this->current)->problems['custom_slug'];

    expect($problem->reason)->toBe(LoginSettingsProblem::REASON_ADDRESS)
        ->and($problem->detail)->toBe(LoginSlug::REASON_RESERVED);
});

it('says hiding wp-login.php needs the address when the request hides it and clears the address (FR-013)', function () {
    $problem = LoginSettingsInput::read(['hide_wp_login' => true, 'custom_slug' => ''], $this->current)->problems['custom_slug'];

    expect($problem->reason)->toBe(LoginSettingsProblem::REASON_ADDRESS_NEEDED_TO_HIDE)
        ->and($problem->detail)->toBe(LoginSlug::REASON_EMPTY);
});

it('turns the store\'s refusal into the same problem (FR-013)', function () {
    $problem = LoginSettingsProblem::fromRefusal(LoginSettingsRefused::addressNeededToHide(LoginSlug::REASON_FORMAT));

    expect($problem->field)->toBe('custom_slug')
        ->and($problem->reason)->toBe(LoginSettingsProblem::REASON_ADDRESS_NEEDED_TO_HIDE)
        ->and($problem->detail)->toBe(LoginSlug::REASON_FORMAT);
});

it('reports every refused field of one request, so nothing of it is saved', function () {
    $input = LoginSettingsInput::read([
        'max_attempts'    => 9,
        'lockout_minutes' => 0,
        'trusted_proxies' => ['10.0.0.0/33'],
    ], $this->current);

    expect(array_keys($input->problems))->toBe(['lockout_minutes', 'trusted_proxies'])
        ->and($input->refused())->toBeTrue();
});
