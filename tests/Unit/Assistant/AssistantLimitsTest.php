<?php

/**
 * The CoreX Assistant's abuse limits (spec 129, User Story 5): who a visitor is, how many questions
 * they and the site may ask, one question at a time, and the middleware that applies all three.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

use Corex\Assistant\Http\AssistantLimitsMiddleware;
use Corex\Assistant\Limits\VisitorKey;
use Corex\Assistant\Limits\VisitorLimits;
use Corex\Assistant\Limits\VisitorLock;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;

/**
 * Limits over an array the test reads back, at a moment the test moves.
 *
 * @param array{hour: int, day: int, site?: int} $caps a visitor's an hour and a day (the site's is $sitePerDay)
 */
function assistantLimits(array &$store, int $sitePerDay = 600, ?int &$now = null, array $caps = ['hour' => 20, 'day' => 60]): VisitorLimits
{
    $now ??= 86400 * 100 + 7200;

    return new VisitorLimits(
        static function (string $key) use (&$store): mixed {
            return $store[$key] ?? false;
        },
        static function (string $key, int $value) use (&$store): void {
            $store[$key] = $value;
        },
        static function () use (&$now): int {
            return $now;
        },
        static fn (): array => $caps + ['site' => $sitePerDay],
    );
}

/**
 * A lock over an array of rows that behaves as the database does: a row is claimed once, and taken over
 * only while it still holds the time that was read.
 */
function assistantLock(array &$rows, int &$now): VisitorLock
{
    return new VisitorLock(
        static function (string $name, int $at) use (&$rows): bool {
            if (isset($rows[$name])) {
                return false;
            }
            $rows[$name] = $at;

            return true;
        },
        static function (string $name) use (&$rows): int {
            return $rows[$name] ?? 0;
        },
        static function (string $name, int $was, int $at) use (&$rows): bool {
            if (($rows[$name] ?? null) !== $was) {
                return false;
            }
            $rows[$name] = $at;

            return true;
        },
        static function (string $name, int $at) use (&$rows): void {
            if (($rows[$name] ?? null) === $at) {
                unset($rows[$name]);
            }
        },
        static function () use (&$now): int {
            return $now;
        },
    );
}

it('knows a visitor by a salted hash, never by the address', function () {
    $key = VisitorKey::of('203.0.113.9', 'salt');

    expect($key)->toMatch('/^[0-9a-f]{24}$/')
        ->and(VisitorKey::of('203.0.113.9', 'other salt'))->not->toBe($key)
        ->and(VisitorKey::of('203.0.113.10', 'salt'))->not->toBe($key);
});

it('counts every address of one IPv6 /64 as one visitor', function () {
    $a = VisitorKey::of('2001:db8:aaaa:bbbb:1::1', 'salt');

    expect(VisitorKey::of('2001:db8:aaaa:bbbb:ffff:ffff:ffff:ffff', 'salt'))->toBe($a)
        ->and(VisitorKey::of('2001:db8:aaaa:bbbc::1', 'salt'))->not->toBe($a);
});

it('counts an IPv4 visitor a dual-stack server reports in IPv6 form as that IPv4 visitor', function () {
    expect(VisitorKey::of('::ffff:203.0.113.9', 'salt'))->toBe(VisitorKey::of('203.0.113.9', 'salt'))
        ->and(VisitorKey::of('::ffff:198.51.100.7', 'salt'))->not->toBe(VisitorKey::of('::ffff:203.0.113.9', 'salt'));
});

it('lets a visitor ask twenty questions an hour, and does not count the ones it refuses', function () {
    $store = [];
    $limits = assistantLimits($store);

    $allowed = 0;
    for ($i = 0; $i < 25; $i++) {
        $allowed += $limits->allow('visitor') ? 1 : 0;
    }

    expect($allowed)->toBe(20)
        ->and($limits->siteToday())->toBe(20)
        ->and($limits->allow('someone-else'))->toBeTrue();
});

it('lets a visitor ask sixty questions a day, whatever the hour', function () {
    $store = [];
    $now = 86400 * 100;
    $limits = assistantLimits($store, 600, $now);

    $allowed = 0;
    for ($hour = 0; $hour < 5; $hour++) {
        $now = 86400 * 100 + $hour * 3600;
        for ($i = 0; $i < 20; $i++) {
            $allowed += $limits->allow('visitor') ? 1 : 0;
        }
    }

    expect($allowed)->toBe(60);
});

it('holds a visitor to the caps the owner set on the screen', function () {
    $store = [];
    $now = 86400 * 100;
    $limits = assistantLimits($store, 600, $now, ['hour' => 2, 'day' => 3]);

    $first = [$limits->allow('visitor'), $limits->allow('visitor'), $limits->allow('visitor')];
    $now += 3600;
    $later = [$limits->allow('visitor'), $limits->allow('visitor')];

    expect($first)->toBe([true, true, false])
        ->and($later)->toBe([true, false]);
});

it('reads the caps once, when a question first needs them', function () {
    $store = [];
    $reads = 0;
    $limits = new VisitorLimits(
        static fn (string $key): mixed => $store[$key] ?? false,
        static function (string $key, int $value) use (&$store): void {
            $store[$key] = $value;
        },
        static fn (): int => 86400 * 100,
        static function () use (&$reads): array {
            $reads++;

            return ['hour' => 20, 'day' => 60, 'site' => 600];
        },
    );

    expect($reads)->toBe(0);
    $limits->allow('a');
    $limits->allow('b');
    expect($reads)->toBe(1);
});

it('stops the whole site at its daily cap', function () {
    $store = [];
    $limits = assistantLimits($store, 3);

    $answers = array_map(static fn (string $visitor): bool => $limits->allow($visitor), ['a', 'b', 'c', 'd']);

    expect($answers)->toBe([true, true, true, false])
        ->and($limits->sitePerDay())->toBe(3);
});

it('holds one question per visitor at a time', function () {
    $rows = [];
    $now = 1000;
    $lock = assistantLock($rows, $now);

    expect($lock->take('v'))->toBeTrue()
        ->and($lock->take('v'))->toBeFalse()
        ->and($lock->take('w'))->toBeTrue();

    $lock->release('v');
    expect($lock->take('v'))->toBeTrue();
});

it('takes over a lock only once it is older than its request could run', function () {
    $rows = ['corex_ai_lock_v' => 1000];
    $now = 1000 + VisitorLock::STALE_SECONDS;
    $lock = assistantLock($rows, $now);

    expect($lock->take('v'))->toBeFalse();

    $now++;
    expect($lock->take('v'))->toBeTrue()
        ->and($rows['corex_ai_lock_v'])->toBe($now);
});

it('claims a lock that was released between its claim and its read', function () {
    $claims = 0;
    $lock = new VisitorLock(
        static function () use (&$claims): bool {
            return ++$claims === 2;
        },
        static fn (): int => 0,
        static fn (): bool => false,
        static function (): void {
        },
        static fn (): int => 1000,
    );

    expect($lock->take('v'))->toBeTrue()
        ->and($claims)->toBe(2);
});

it('cannot release a lock another request took over after its own went stale', function () {
    $rows = [];
    $now = 1000;
    $slow = assistantLock($rows, $now);
    $slow->take('v');

    $now += VisitorLock::STALE_SECONDS + 1;
    $next = assistantLock($rows, $now);
    expect($next->take('v'))->toBeTrue();

    $slow->release('v');
    expect($rows)->toBe(['corex_ai_lock_v' => $now]);

    $next->release('v');
    expect($rows)->toBe([]);
});

it('lets only one of two requests take over the same stale lock', function () {
    $rows = ['corex_ai_lock_v' => 1000];
    $now = 2000;
    $lock = assistantLock($rows, $now);
    $other = new VisitorLock(
        static fn (): bool => false,
        static fn (): int => 1000,
        static function (string $name, int $was, int $at) use (&$rows): bool {
            if ($rows[$name] !== $was) {
                return false;
            }
            $rows[$name] = $at;

            return true;
        },
        static function (): void {
        },
        static fn (): int => 2001,
    );

    expect($lock->take('v'))->toBeTrue()
        ->and($other->take('v'))->toBeFalse();
});

it('refuses with the limits status and releases the lock after an answer', function () {
    $rows = [];
    $now = 1000;
    $store = [];
    $middleware = new AssistantLimitsMiddleware(assistantLock($rows, $now), assistantLimits($store, 1));
    $request = new Request(method: 'POST', throttleKey: 'visitor');

    $first = $middleware->process($request, static fn (): Response => Response::ok(['reply' => 'Hi']));
    $second = $middleware->process($request, static fn (): Response => Response::ok(['reply' => 'Hi']));

    expect($first->isOk())->toBeTrue()
        ->and($second->status)->toBe(AssistantLimitsMiddleware::REFUSED)
        ->and($rows)->toBe([]);
});

it('releases the lock when the answer throws', function () {
    $rows = [];
    $now = 1000;
    $store = [];
    $middleware = new AssistantLimitsMiddleware(assistantLock($rows, $now), assistantLimits($store));

    expect(static fn () => $middleware->process(new Request(method: 'POST', throttleKey: 'visitor'), static function (): Response {
        throw new RuntimeException('the chain broke');
    }))->toThrow(RuntimeException::class);
    expect($rows)->toBe([]);
});

it('refuses a second question while the first is answered, counting nothing and keeping the other’s lock', function () {
    $rows = ['corex_ai_lock_visitor' => 1000];
    $now = 1001;
    $store = [];
    $middleware = new AssistantLimitsMiddleware(assistantLock($rows, $now), assistantLimits($store));

    $response = $middleware->process(new Request(method: 'POST', throttleKey: 'visitor'), static fn (): Response => Response::ok([]));

    expect($response->status)->toBe(AssistantLimitsMiddleware::REFUSED)
        ->and($store)->toBe([])
        ->and($rows)->toBe(['corex_ai_lock_visitor' => 1000]);
});
