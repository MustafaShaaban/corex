<?php

/**
 * The PHP half of the FR-001 parity check (spec 076, T021).
 *
 * This and `adminDateTime.test.js` read the *same committed fixture* and assert the *same expected
 * strings*. Neither suite computes what the other should produce — both are measured against a
 * third thing — so a change to either implementation alone turns one of them red.
 *
 * That structure is the point. "Both sides format dates the same way" is the kind of claim that
 * quietly stops being true, and the way it usually stops being true is that somebody improves one
 * side. Here, improving one side breaks a test.
 *
 * @package Corex\Tests\Integration\Support\DateTime
 */

declare(strict_types=1);

use Corex\Support\DateTime\AdminDateTime;
use Corex\Support\DateTime\AdminDateTimeFormatter;

/**
 * @return array<string, mixed>
 */
function parityFixture(): array
{
    static $fixture = null;

    if ($fixture === null) {
        $path    = dirname(__DIR__, 3) . '/Fixtures/datetime-parity.json';
        $fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    return $fixture;
}

beforeEach(function () {
    $this->originalTimezone = get_option('timezone_string');
    $this->originalOffset   = get_option('gmt_offset');
    $this->formatter        = new AdminDateTimeFormatter();
});

afterEach(function () {
    update_option('timezone_string', $this->originalTimezone);
    update_option('gmt_offset', $this->originalOffset);
});

it('matches the committed fixture for every case', function () {
    $failures = [];

    foreach (parityFixture()['cases'] as $case) {
        update_option('timezone_string', $case['site']);
        update_option('gmt_offset', 0);

        foreach ($case['expected'] as $kind => $expected) {
            $actual = $this->formatter->format($case['value'], $kind)->human;

            if ($actual !== $expected) {
                // Collected rather than asserted one at a time: when a format change breaks parity
                // it usually breaks several cases, and seeing all of them names the cause
                // immediately. One failing assertion at a time hides the shape of the break.
                $failures[] = sprintf(
                    '%s [%s]: expected "%s", got "%s"',
                    $case['name'],
                    $kind,
                    $expected,
                    $actual,
                );
            }
        }
    }

    expect($failures)->toBe([], "Parity failures:\n" . implode("\n", $failures));
});

it('treats every absent value in the fixture as absent', function () {
    update_option('timezone_string', 'Africa/Cairo');

    foreach (parityFixture()['absent'] as $case) {
        $formatted = $this->formatter->format($case['value'], AdminDateTime::FULL, 'Not recorded');

        expect($formatted->isPresent)
            ->toBeFalse("'{$case['name']}' should be absent")
            ->and($formatted->machine)
            ->toBe('', "'{$case['name']}' should carry no machine value");
    }
});

it('exports a client config the browser can reproduce this with', function () {
    update_option('timezone_string', 'Africa/Cairo');

    $config = $this->formatter->clientConfig();

    expect($config['timezone']['name'])->toBe('Africa/Cairo')
        ->and($config['months'])->toHaveCount(12)
        ->and($config['months'][7])->toBe('August')
        ->and($config['meridiem']['PM'])->toBe('PM')
        ->and($config['patterns']['date'])->toBe('j F Y')
        ->and($config['patterns']['time'])->toBe('g:i A')
        ->and($config['patterns']['connector'])->toBe('%1$s at %2$s');
});

it('exports an offset rather than a name for a site configured without one', function () {
    update_option('timezone_string', '');
    update_option('gmt_offset', 5.5);

    $config = $this->formatter->clientConfig();

    // The browser needs to know it must do epoch arithmetic rather than look up a zone that does
    // not exist. Handing it 'UTC+5:30' as a name would make `Intl` throw.
    expect($config['timezone']['name'])->toBeNull()
        ->and($config['timezone']['offsetMinutes'])->toBe(330);
});

it('exports nothing sensitive', function () {
    $flattened = strtolower(json_encode($this->formatter->clientConfig(), JSON_THROW_ON_ERROR));

    foreach (['password', 'secret', 'token', 'nonce', 'key', 'salt', 'hash'] as $forbidden) {
        expect($flattened)->not->toContain($forbidden);
    }
});
