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

it('asks to change nothing when the request sends nothing it knows (FR-015)', function () {
    $input = LoginSettingsInput::read(['_wpnonce' => 'x', 'login_url' => 'https://example.test/'], $this->current);

    expect($input->problems)->toBe([])
        ->and($input->changes)->toBe([])
        ->and($input->switches)->toBeNull();
});

it('turns the minutes and days the screen shows into the stored seconds and days (FR-015)', function () {
    $input = LoginSettingsInput::read([
        'window_minutes'           => 10,
        'lockout_minutes'          => 15,
        'retention_days'           => 45,
        'successful_login_logging' => false,
    ], $this->current);

    expect($input->problems)->toBe([])
        ->and($input->changes)->toBe([
            'window_seconds'           => 600,
            'lockout_seconds'          => 900,
            'retain_days'              => 45,
            'successful_login_logging' => false,
        ]);
});

it('still takes the seconds the previous screen sends (FR-015, until slice 10)', function () {
    $input = LoginSettingsInput::read(['window_seconds' => 300, 'lockout_seconds' => 900], $this->current);

    expect($input->problems)->toBe([])
        ->and($input->changes)->toBe(['window_seconds' => 300, 'lockout_seconds' => 900]);
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

it('changes one switch and keeps the other two as they are (FR-013, FR-015)', function () {
    $switches = LoginSettingsInput::read(['hide_admin' => false], $this->current)->switches;

    expect($switches)->toEqual(new LoginSwitches(hideWpLogin: false, hideAdmin: false, limitAttempts: true));
});

it('reads the previous screen\'s two keys in their old meaning (FR-014)', function () {
    $switches = LoginSettingsInput::read(['enabled' => true, 'block_default_endpoints' => true], $this->current)->switches;

    expect($switches)->toEqual(new LoginSwitches(true, true, true));
});

it('refuses the switches and the previous screen\'s keys in one request', function () {
    $input = LoginSettingsInput::read(['hide_admin' => true, 'enabled' => false], $this->current);

    expect($input->problems['enabled']->reason)->toBe(LoginSettingsProblem::REASON_TWO_SHAPES)
        ->and($input->switches)->toBeNull();
});

it('refuses a length sent in minutes and in seconds at once', function () {
    $input = LoginSettingsInput::read(['window_minutes' => 5, 'window_seconds' => 300], $this->current);

    expect($input->problems['window_minutes']->reason)->toBe(LoginSettingsProblem::REASON_TWO_UNITS);
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
    expect(LoginSettingsInput::read(['trusted_proxies' => ''], $this->current)->changes)
        ->toBe(['trusted_proxy_ranges' => [], 'trusted_proxy_mode' => false]);
});

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
    expect(LoginSettingsInput::read(['trusted_proxies' => ['203.0.113.0/24'], 'trusted_proxy_mode' => false], $this->current)->changes)
        ->toBe(['trusted_proxy_ranges' => ['203.0.113.0/24'], 'trusted_proxy_mode' => false]);
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
