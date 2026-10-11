<?php

/**
 * The database step of an installation, on real WordPress (spec 107, US3; FR-030, FR-032,
 * FR-035; plan D11, D19).
 *
 * The request that finishes an installation runs the release just installed. It brings the
 * schema to what that release registers, through the runner the automatic check and
 * `wp corex migrate` use, and says what moved: each component with the version it was at and
 * the version it is at now.
 *
 * The registry here holds one component of the test's own, so nothing of the site's real
 * schema is read or changed. The table it makes is a real one, and is dropped afterwards.
 *
 * @package Corex\Tests\Integration\Releases
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Releases\ReleaseFinisher;
use Corex\Config\Releases\ReleaseRefused;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\SchemaComponent;
use Corex\Database\Schema\SchemaMigrator;
use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\SchemaVersionStore;
use Corex\Database\Schema\SiteMigrationRunner;
use Corex\Database\Schema\Table;
use Corex\Events\EventDispatcher;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\NetworkContext;
use Corex\Multisite\SiteScope;

const FINISHER_VERSION_OPTION = 'corex_release_fixture_schema_version';

beforeEach(function () {
    $this->table    = 'release_fixture_' . bin2hex(random_bytes(3));
    $this->registry = new SchemaRegistry();
    $this->migrator = new Migrator();

    $this->finisherOver = function (SchemaMigrator $migrator): ReleaseFinisher {
        $container = Boot::app()->container();

        return new ReleaseFinisher(
            new SiteMigrationRunner(
                $migrator,
                $this->registry,
                $container->make(SchemaVersionStore::class),
                $container->make(SiteScope::class),
                $container->make(NetworkContext::class),
                $container->make(EventDispatcher::class),
            ),
            $this->registry,
            $container->make(SchemaVersionStore::class),
            $container->make(SiteScope::class),
            $container->make(NetworkContext::class),
            $container->make(MultisiteContext::class),
        );
    };

    $this->releaseRegisters = function (string $version): void {
        $this->registry->register(new SchemaComponent(
            'release-fixture',
            $version,
            [(new Table($this->table))->id()->string('note')],
            FINISHER_VERSION_OPTION,
        ));
    };

    delete_option(FINISHER_VERSION_OPTION);
});

afterEach(function () {
    $this->migrator->drop($this->table);
    delete_option(FINISHER_VERSION_OPTION);
});

it('brings a component that is behind to the release, and lists it with where it was and where it is', function () {
    update_option(FINISHER_VERSION_OPTION, '3', false);
    ($this->releaseRegisters)('4');

    $finished = ($this->finisherOver)($this->migrator)->run(0);

    expect($finished)->toBe([
        'changes' => [['site' => get_current_blog_id(), 'component' => 'release-fixture', 'from' => '3', 'to' => '4']],
        'next'    => 1,
        'done'    => true,
    ])
        ->and($this->migrator->exists($this->table))->toBeTrue()
        ->and(get_option(FINISHER_VERSION_OPTION))->toBe('4');
});

it('says a component the site never had is new, with nothing for where it was', function () {
    ($this->releaseRegisters)('1');

    $finished = ($this->finisherOver)($this->migrator)->run(0);

    expect($finished['changes'])->toBe([
        ['site' => get_current_blog_id(), 'component' => 'release-fixture', 'from' => '', 'to' => '1'],
    ]);
});

it('says no change was needed when the schema is already the release\'s', function () {
    update_option(FINISHER_VERSION_OPTION, '4', false);
    ($this->releaseRegisters)('4');

    expect(($this->finisherOver)($this->migrator)->run(0))->toBe(['changes' => [], 'next' => 1, 'done' => true]);
});

it('finds nothing left to do when the automatic check got there first', function () {
    // The tab was closed after the swap; an admin page ran the runner; the screen finishes later.
    update_option(FINISHER_VERSION_OPTION, '3', false);
    ($this->releaseRegisters)('4');
    ($this->finisherOver)($this->migrator)->run(0);

    expect(($this->finisherOver)($this->migrator)->run(0))->toBe(['changes' => [], 'next' => 1, 'done' => true]);
});

it('names a change that failed, and does not say the schema moved', function () {
    update_option(FINISHER_VERSION_OPTION, '3', false);
    ($this->releaseRegisters)('4');

    // A database that will not make the table: the runner finds it missing afterwards.
    $willNotCreate = new class () implements SchemaMigrator {
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
            return false;
        }
    };

    expect(fn () => ($this->finisherOver)($willNotCreate)->run(0))
        ->toThrow(function (ReleaseRefused $refused) {
            expect($refused->reason)->toBe(ReleaseFinisher::FAILED)
                ->and($refused->getMessage())->toContain('release-fixture')
                ->and($refused->getMessage())->toContain($this->table);
        });

    expect(get_option(FINISHER_VERSION_OPTION))->toBe('3');
});
