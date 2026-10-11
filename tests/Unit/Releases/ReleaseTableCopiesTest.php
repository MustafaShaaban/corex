<?php

/**
 * Unit test: a copy of CoreX's tables as they were, made before a release changes them (spec
 * 107, FR-034, FR-041; plan D10).
 *
 * One copy for each table in the schema registry, named for the table and the minute it was
 * made, so it is found by its name and nothing has to be kept anywhere a release replaces. It
 * is there for a person to read from. Nothing here can put a copy back over a live table: the
 * boundary this works through has no way to say it.
 *
 * The database is one in memory that writes down what it was asked.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\ReleaseRefused;
use Corex\Config\Releases\ReleaseTableCopier;
use Corex\Config\Releases\ReleaseTableCopies;
use Corex\Database\Schema\SchemaComponent;
use Corex\Database\Schema\SchemaMigrator;
use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\Table;
use Corex\Tests\Support\RecordingTableCopier;

/** The site's tables: two of CoreX's with rows in them, one of WordPress's. */
const THE_LIVE_TABLES = [
    'wp_corex_activity'      => ['signed in', 'saved a form'],
    'wp_corex_notifications' => ['a lead arrived'],
    'wp_posts'               => ['a page'],
];

function namesTablesAsThisSiteDoes(): SchemaMigrator
{
    return new class () implements SchemaMigrator {
        public function fullName(string $name): string
        {
            return 'wp_corex_' . $name;
        }

        public function create(Table $table): void
        {
        }

        public function drop(string $name): void
        {
        }

        public function exists(string $name): bool
        {
            return true;
        }
    };
}

beforeEach(function () {
    Functions\when('__')->returnArg();

    $this->registry = new SchemaRegistry();
    $this->registry->register(new SchemaComponent('product-foundation', '7', [
        new Table('activity'),
        new Table('notifications'),
        // Registered by the release that is running, and not made yet on this site.
        new Table('reading_events'),
    ], 'corex_schema_version'));

    $this->database = new RecordingTableCopier(THE_LIVE_TABLES);
    $this->at       = new DateTimeImmutable('2026-10-11 15:30:42', new DateTimeZone('UTC'));

    $this->copies = fn (float $seconds = 8.0): ReleaseTableCopies => new ReleaseTableCopies(
        $this->database,
        $this->registry,
        namesTablesAsThisSiteDoes(),
        $seconds,
    );

    $this->copyAll = function (?DateTimeImmutable $at = null): void {
        for ($cursor = 0, $of = 1; $cursor < $of;) {
            ['cursor' => $cursor, 'of' => $of] = ($this->copies)()->copy($cursor, $at ?? $this->at);
        }
    };
});

it('copies each registered table that is there, under a name that says which table and when', function () {
    ($this->copyAll)();

    expect($this->database->tables)->toMatchArray([
        'wp_corex_activity_cxrel_202610111530'      => ['signed in', 'saved a form'],
        'wp_corex_notifications_cxrel_202610111530' => ['a lead arrived'],
    ])
        // Not WordPress's own, and not a table that does not exist yet.
        ->and(array_keys($this->database->tables))->toHaveCount(5);
});

it('lists what it keeps, with when each was made and when it goes', function () {
    ($this->copyAll)();

    expect(($this->copies)()->kept())->toBe([
        ['table' => 'wp_corex_activity', 'copy' => 'wp_corex_activity_cxrel_202610111530', 'made_at' => '2026-10-11T15:30:00+00:00', 'goes_at' => '2026-10-25T15:30:00+00:00'],
        ['table' => 'wp_corex_notifications', 'copy' => 'wp_corex_notifications_cxrel_202610111530', 'made_at' => '2026-10-11T15:30:00+00:00', 'goes_at' => '2026-10-25T15:30:00+00:00'],
    ]);
});

it('copies a table a request, when a request has no time for more, and says how far it has got', function () {
    $first = ($this->copies)(0.0)->copy(0, $this->at);

    expect($first)->toBe(['cursor' => 1, 'of' => 2])
        ->and(isset($this->database->tables['wp_corex_notifications_cxrel_202610111530']))->toBeFalse();

    expect(($this->copies)(0.0)->copy($first['cursor'], $this->at))->toBe(['cursor' => 2, 'of' => 2])
        ->and(isset($this->database->tables['wp_corex_notifications_cxrel_202610111530']))->toBeTrue();
});

it('keeps one set of copies: the ones made for the installation before go when the next begins', function () {
    ($this->copyAll)(new DateTimeImmutable('2026-09-30 09:00:00', new DateTimeZone('UTC')));
    ($this->copyAll)();

    expect(array_column(($this->copies)()->kept(), 'copy'))->toBe([
        'wp_corex_activity_cxrel_202610111530',
        'wp_corex_notifications_cxrel_202610111530',
    ]);
});

it('never touches a live table: what it removes is only ever a copy', function () {
    ($this->copyAll)(new DateTimeImmutable('2026-09-30 09:00:00', new DateTimeZone('UTC')));
    ($this->copyAll)();
    ($this->copies)()->discard();

    $dropped = array_map(
        static fn (string $asked): string => substr($asked, 5),
        array_filter($this->database->asked, static fn (string $asked): bool => str_starts_with($asked, 'drop ')),
    );

    expect($this->database->tables)->toBe(THE_LIVE_TABLES);

    foreach ($dropped as $table) {
        expect($table)->toMatch('/_cxrel_\d{12}$/');
    }

    // And the boundary it works through cannot rename a table at all.
    expect(get_class_methods(ReleaseTableCopier::class))->toBe(['tablesStartingWith', 'exists', 'copy', 'drop']);
});

it('refuses to go on when a table cannot be copied whole, and keeps nothing of the attempt', function () {
    $this->database->failsOn = 'wp_corex_notifications';

    expect(fn () => ($this->copyAll)())
        ->toThrow(function (ReleaseRefused $refused) {
            expect($refused->reason)->toBe(ReleaseTableCopies::FAILED)
                ->and($refused->getMessage())->toContain('wp_corex_notifications');
        });

    expect($this->database->tables)->toBe(THE_LIVE_TABLES);
});

it('names a copy within what MySQL allows for a name, and still finds it', function () {
    $this->registry->register(new SchemaComponent('newsletter', '1', [
        new Table('newsletter_subscriber_preference_history_archive'),
    ], 'corex_newsletter_schema_version'));
    $long = 'wp_corex_newsletter_subscriber_preference_history_archive';
    $this->database->tables[$long] = ['a row'];

    ($this->copyAll)();

    $kept = array_values(array_filter(($this->copies)()->kept(), static fn (array $copy): bool => $copy['table'] === $long));

    expect(strlen($long . '_cxrel_202610111530'))->toBeGreaterThan(64)
        ->and($kept)->toHaveCount(1)
        ->and(strlen($kept[0]['copy']))->toBeLessThanOrEqual(64)
        ->and($kept[0]['copy'])->toEndWith('_cxrel_202610111530')
        ->and($this->database->tables[$kept[0]['copy']])->toBe(['a row']);
});
