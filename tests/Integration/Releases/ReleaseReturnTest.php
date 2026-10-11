<?php

/**
 * Going back to the release before, and what the Releases screen keeps and shows (spec 107,
 * US4 and US5; FR-040 to FR-045, FR-051; plan D9, D10, D19; T060 to T064).
 *
 * The site whose folders move is one in the temp folder; WordPress, its REST server and its
 * database are real. A release is installed through the routes, and then left: from the
 * screen, or through the recovery link, which is run here as WordPress would run it when CoreX
 * could not help, in a PHP process of its own.
 *
 * @package Corex\Tests\Integration\Releases
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;
use Corex\Boot;
use Corex\Config\Releases\InstalledRelease;
use Corex\Config\Releases\ReleaseFinisher;
use Corex\Config\Releases\ReleaseProgress;
use Corex\Config\Releases\ReleaseReturn;
use Corex\Config\Releases\ReleasesController;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\SchemaComponent;
use Corex\Database\Schema\SchemaMigrator;
use Corex\Database\Schema\Table;
use Corex\Tests\Support\ReleaseInstaller;

const RETURN_VERSION_OPTION = 'corex_release_return_fixture_schema_version';

/**
 * What a browser gets from a WordPress that loads the recovery plugin and nothing of CoreX.
 */
function openTheRecoveryLink(string $mustUsePlugin, string $key): string
{
    $runner = tempnam(sys_get_temp_dir(), 'corex-wp-') . '.php';
    file_put_contents($runner, '<?php define("ABSPATH", __DIR__ . "/"); $_GET = ' . var_export(['corex_release_key' => $key, 'corex_release_recover' => '1'], true) . '; '
        . 'include ' . var_export($mustUsePlugin, true) . ';');

    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner), $output);
    unlink($runner);
    @unlink(substr($runner, 0, -4));

    return implode("\n", $output);
}

/**
 * @param array<string,string> $files
 *
 * @return array<string,string>
 */
function byPath(array $files): array
{
    ksort($files);

    return $files;
}

beforeEach(function () {
    $admins      = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->admin = (int) ($admins[0] ?? 0);
    wp_set_current_user($this->admin);

    $this->table    = 'return_fixture_' . bin2hex(random_bytes(3));
    $this->migrator = new Migrator();
    $this->sites    = [];

    $this->site = function (?SchemaMigrator $migrator = null): ReleaseInstaller {
        $site          = new ReleaseInstaller(ReleaseInstaller::RUNNING_0_44, migrator: $migrator);
        $this->sites[] = $site;

        Boot::app()->container()->instance(ReleasesController::class, $site->controller);
        $GLOBALS['wp_rest_server'] = null;

        return $site;
    };

    $this->overview = static fn (): array => ReleaseInstaller::ask('GET', '/releases')->get_data()['data'];

    delete_option(InstalledRelease::OPTION);
    delete_option(RETURN_VERSION_OPTION);
});

afterEach(function () {
    Boot::app()->container()->singleton(ReleasesController::class);
    $GLOBALS['wp_rest_server'] = null;

    foreach ($this->sites as $site) {
        $site->copies->discard();
        $site->remove();
    }
    $this->migrator->drop($this->table);
    delete_option(InstalledRelease::OPTION);
    delete_option(RETURN_VERSION_OPTION);
});

it('goes back from the screen: the folders as they were, on record, and no change to the database reversed', function () {
    $site = ($this->site)();
    // The release brings a table, made in its database step.
    $site->registry->register(new SchemaComponent('return-fixture', '1', [(new Table($this->table))->id()->string('note')], RETURN_VERSION_OPTION));
    $site->installTheRelease();

    $kept = ($this->overview)()['previous'];

    expect($kept)->toMatchArray([
        'status'      => 'kept',
        'version'     => '0.44.0',
        'replaced_by' => '0.45.0',
        'phrase'      => '0.44.0',
    ]);

    // Not on a version that was not typed.
    $unconfirmed = ReleaseInstaller::ask('POST', '/releases/return', ['phrase' => '0.45.0']);
    expect($unconfirmed->get_status())->toBe(422)
        ->and($unconfirmed->get_data()['reason'])->toBe(ReleaseReturn::NOT_CONFIRMED)
        ->and($site->installed->version())->toBe('0.45.0');

    $returned = ReleaseInstaller::ask('POST', '/releases/return', ['phrase' => '0.44.0'])->get_data();

    expect($returned['ok'])->toBeTrue()
        ->and($returned['data'])->toMatchArray(['version' => '0.44.0', 'left' => '0.45.0', 'database_changes_reversed' => 0])
        // Every file, the one the newer release did not hold and the plugin it dropped among them.
        ->and($site->files())->toBe(byPath(ReleaseInstaller::RUNNING_0_44))
        ->and($site->installed->version())->toBe('0.44.0')
        ->and($site->maintenance->isOn())->toBeFalse()
        // There is nothing left to go back to, so the way back is gone too.
        ->and($site->recovery->isWritten())->toBeFalse()
        ->and(glob($site->root . '/wp-content/corex-releases/staging/*'))->toBe([])
        ->and(($this->overview)()['previous'])->toBeNull()
        // What the release did to the database is as it left it.
        ->and($this->migrator->exists($this->table))->toBeTrue()
        ->and(get_option(RETURN_VERSION_OPTION))->toBe('1');

    $logged = $site->store->log();
    expect(end($logged))->toMatchArray(['event' => 'returned', 'version' => '0.44.0', 'from' => '0.45.0', 'by' => $this->admin])
        ->and(array_map(static fn (ActivityEvent $event): string => $event->kind, $site->activity->events))->toBe(['release.installed', 'release.returned']);
});

it('goes back from an installation whose database step failed, and leaves nothing of it standing', function () {
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
    $site = ($this->site)($willNotCreate);
    $site->confirmTheRelease();
    ReleaseInstaller::stepUntil(ReleaseProgress::FINISH);
    $site->registry->register(new SchemaComponent('return-fixture', '1', [new Table($this->table)], RETURN_VERSION_OPTION));

    expect(ReleaseInstaller::ask('POST', '/releases/install/finish')->get_data()['reason'])->toBe(ReleaseFinisher::FAILED)
        ->and($site->maintenance->isOn())->toBeTrue();

    // The screen offers the release before while the installation stands where it failed.
    expect(($this->overview)()['previous']['version'])->toBe('0.44.0');

    ReleaseInstaller::ask('POST', '/releases/return', ['phrase' => '0.44.0']);

    expect($site->files())->toBe(byPath(ReleaseInstaller::RUNNING_0_44))
        ->and($site->maintenance->isOn())->toBeFalse()
        ->and($site->store->state())->toBe([])
        ->and($site->installed->version())->toBe('0.44.0')
        ->and(($this->overview)()['installation'])->toBeNull();
});

it('finds a return made through the recovery link when the screen is next opened, and puts the record right', function () {
    $site     = ($this->site)();
    $prepared = $site->installTheRelease();

    // The admin does not load. Somebody opens the link they saved.
    $said = openTheRecoveryLink($site->root . '/wp-content/mu-plugins/corex-release-recovery.php', $prepared['key']);

    expect($said)->toContain('No change to the database was reversed.')
        ->and($site->files())->toBe(byPath(ReleaseInstaller::RUNNING_0_44))
        // CoreX was not there to hear of it: the site still says it runs the newer release.
        ->and($site->installed->version())->toBe('0.45.0');

    $overview = ($this->overview)();

    expect($overview['installed']['version'])->toBe('0.44.0')
        ->and($overview['previous'])->toBeNull()
        ->and($site->recovery->isWritten())->toBeFalse()
        ->and(glob($site->root . '/wp-content/corex-releases/staging/*'))->toBe([]);

    $logged = $site->store->log();
    expect(end($logged))->toMatchArray(['event' => 'returned', 'version' => '0.44.0', 'from' => '0.45.0', 'by' => 0, 'through' => 'recovery_link']);

    // Written down once, however often the screen is opened afterwards.
    ($this->overview)();
    expect($site->store->log())->toHaveCount(count($logged));

    $entry = $site->activity->events[1];
    expect($entry->kind)->toBe('release.returned')
        ->and($entry->actorKind)->toBe(ActivityEvent::ACTOR_SYSTEM);
});

it('has nothing to go back to before a release has been installed, and will not go back over one being unpacked', function () {
    $site = ($this->site)();

    expect(($this->overview)()['previous'])->toBeNull()
        ->and(ReleaseInstaller::ask('POST', '/releases/return', ['phrase' => '0.44.0'])->get_data())->toMatchArray(['ok' => false, 'reason' => ReleaseReturn::NOTHING_KEPT]);

    // A release was installed and another is on its way in: that one is cancelled first.
    $site->installTheRelease();
    $site->confirmTheRelease();

    expect(ReleaseInstaller::ask('POST', '/releases/return', ['phrase' => '0.44.0'])->get_data())->toMatchArray(['ok' => false, 'reason' => ReleaseReturn::INSTALLING]);
});

it('removes, when the screen is opened, a package nobody used and a table copy that has been kept its time', function () {
    global $wpdb;

    $site = ($this->site)();
    $site->registry->register(new SchemaComponent('return-fixture', '1', [(new Table($this->table))->id()->string('note')], RETURN_VERSION_OPTION));
    $this->migrator->create((new Table($this->table))->id()->string('note'));
    update_option(RETURN_VERSION_OPTION, '1', false);

    $incoming = $site->root . '/wp-content/corex-releases/incoming';
    $site->giveTheRelease();
    copy($incoming . '/' . ReleaseInstaller::THE_PACKAGE, $incoming . '/corex-release-acme-0.43.0-20260901-090000.zip');
    file_put_contents($incoming . '/' . str_repeat('c', 64) . '.part', 'half of an upload nobody finished');
    touch($incoming . '/corex-release-acme-0.43.0-20260901-090000.zip', time() - 8 * DAY_IN_SECONDS);
    touch($incoming . '/' . str_repeat('c', 64) . '.part', time() - 8 * DAY_IN_SECONDS);

    // A copy made fifteen days ago, and one made today.
    $live = $this->migrator->fullName($this->table);
    $old  = $live . '_cxrel_' . gmdate('YmdHi', time() - 15 * DAY_IN_SECONDS);
    $new  = $live . '_cxrel_' . gmdate('YmdHi');
    $wpdb->query("CREATE TABLE `$old` LIKE `$live`"); // phpcs:ignore WordPress.DB
    $wpdb->query("CREATE TABLE `$new` LIKE `$live`"); // phpcs:ignore WordPress.DB

    $overview = ($this->overview)();

    expect(array_column($overview['packages'], 'name'))->toBe([ReleaseInstaller::THE_PACKAGE])
        ->and(glob($incoming . '/*.part'))->toBe([])
        ->and($overview['package_days'])->toBe(7)
        ->and(array_column($site->copies->kept(), 'copy'))->toBe([$new]);
});

it('shows what happened here, newest first, with who did it', function () {
    $site = ($this->site)();
    $site->give(Corex\Tests\Support\ReleasePackages::package(['client' => 'globex']), 'corex-release-globex-0.45.0-20261011-120000.zip');
    ReleaseInstaller::ask('POST', '/releases/inspect', ['package' => 'corex-release-globex-0.45.0-20261011-120000.zip']);
    $site->installTheRelease();
    ReleaseInstaller::ask('POST', '/releases/return', ['phrase' => '0.44.0']);

    $record = ($this->overview)()['record'];

    expect(array_column($record, 'event'))->toBe(['returned', 'installed', 'refused'])
        ->and($record[0])->toMatchArray(['version' => '0.44.0', 'from' => '0.45.0', 'by' => $this->admin, 'by_name' => 'User ' . $this->admin])
        ->and($record[1])->toMatchArray(['version' => '0.45.0', 'from' => '0.44.0'])
        ->and($record[2])->toMatchArray(['package' => 'corex-release-globex-0.45.0-20261011-120000.zip', 'reason' => 'other_client']);
});
