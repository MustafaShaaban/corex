<?php

/**
 * The access request table takes one open request per person per ability or area.
 *
 * Against the real table, because the promise is about two asks that arrive together: what
 * decides it is a lock and two statements in MySQL, which a store written for a unit test does
 * not have.
 *
 * @package Corex\Tests\Integration\Access
 */

declare(strict_types=1);

use Corex\Access\AccessRequestStore;
use Corex\Access\CorexAbility;
use Corex\Boot;
use Corex\Config\Access\AccessTables;
use Corex\Database\Schema\Migrator;

beforeEach(function () {
    $container = Boot::app()->container();
    $this->migrator = $container->make(Migrator::class);
    foreach ($container->make(AccessTables::class)->schemas() as $schema) {
        $this->migrator->create($schema);
    }

    $this->store = $container->make(AccessRequestStore::class);
    $this->table = $this->migrator->fullName(AccessTables::REQUESTS);

    // Nobody's id: the store keeps a number and never looks the person up, so no account has to
    // be made, and deleting by it in `afterEach` cannot reach a row another test or a person filed.
    // The next number up is the one other person a test asks as.
    $this->requesterId = random_int(900_000_000, 999_999_998);

    $this->ask = fn (?string $abilityKey, ?string $areaKey, string $expires = '+7 days'): ?int => $this->store
        ->createUnlessPending(
            $this->requesterId,
            $abilityKey,
            $areaKey,
            'Needed for my work.',
            new DateTimeImmutable('now'),
            new DateTimeImmutable($expires),
        );
});

afterEach(function () {
    global $wpdb;

    if (isset($this->watch)) {
        remove_filter('query', $this->watch);
    }

    // The requester and the one other person a test asks as.
    $wpdb->query($wpdb->prepare(
        'DELETE FROM ' . $this->table . ' WHERE requester_id IN (%d, %d)',
        $this->requesterId,
        $this->requesterId + 1,
    ));
});

it('stores one request when the same thing is asked for twice', function (?string $abilityKey, ?string $areaKey) {
    global $wpdb;

    $first  = ($this->ask)($abilityKey, $areaKey);
    $second = ($this->ask)($abilityKey, $areaKey);

    expect($first)->toBeInt()
        ->and($second)->toBeNull()
        ->and((int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . $this->table . ' WHERE requester_id = %d',
            $this->requesterId,
        )))->toBe(1);
})->with([
    'an ability' => [CorexAbility::MANAGE_FORMS, null],
    'an area'    => [null, 'forms-flows'],
]);

it('stores a request for something else, or from somebody else, beside the one that is open', function () {
    $fromSomebodyElse = $this->store->createUnlessPending(
        $this->requesterId + 1,
        CorexAbility::MANAGE_FORMS,
        null,
        'Needed for my work.',
        new DateTimeImmutable('now'),
        new DateTimeImmutable('+7 days'),
    );

    expect(($this->ask)(CorexAbility::MANAGE_FORMS, null))->toBeInt()
        ->and(($this->ask)(CorexAbility::MANAGE_DATA, null))->toBeInt()
        // The area an ability sits in is a wider request than the ability, not the same one.
        ->and(($this->ask)(null, 'forms-flows'))->toBeInt()
        ->and($fromSomebodyElse)->toBeInt();
});

it('stores a new request once the earlier one is decided or has run out', function () {
    $decided = ($this->ask)(CorexAbility::MANAGE_FORMS, null);
    $this->store->transition($decided, 'denied', 1, 'Not this week.', new DateTimeImmutable('now'));
    ($this->ask)(CorexAbility::MANAGE_DATA, null, '-1 minute');

    expect(($this->ask)(CorexAbility::MANAGE_FORMS, null))->toBeInt()
        ->and(($this->ask)(CorexAbility::MANAGE_DATA, null))->toBeInt();
});

it('lets no second ask in between finding nothing open and storing the request', function () {
    global $wpdb;

    // Two asks that arrive together both find nothing open, unless the second has to wait for
    // the first to finish. What makes it wait is a lock named for the requester. This reads, at
    // the moment the row is written, whether that lock is held, and afterwards whether it was
    // let go: a lock left held would make the person's next ask wait until it failed.
    $lock = null;
    $heldAtInsert = null;
    $this->watch = function (string $sql) use (&$lock, &$heldAtInsert, $wpdb): string {
        if (preg_match("/GET_LOCK\(\s*'([^']+)'/", $sql, $found) === 1) {
            $lock = $found[1];
        }

        if ($heldAtInsert === null && str_starts_with(ltrim($sql), 'INSERT INTO `' . $this->table . '`')) {
            $heldAtInsert = false;
            $heldAtInsert = $lock !== null
                && $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)) !== null;
        }

        return $sql;
    };
    add_filter('query', $this->watch);

    $stored = ($this->ask)(CorexAbility::MANAGE_FORMS, null);

    expect($stored)->toBeInt()
        ->and($heldAtInsert)->toBeTrue()
        ->and($wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', (string) $lock)))->toBeNull();
});
