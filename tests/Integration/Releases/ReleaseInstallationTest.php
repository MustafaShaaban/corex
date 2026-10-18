<?php

/**
 * A whole installation, asked for as the Releases screen asks, on real WordPress (spec 107, US1
 * and US3; plan D8; T052, T052b).
 *
 * The site whose folders move is one in the temp folder. Everything else is real: the REST
 * server and its routes, the administrator, the options, and the database the release's table
 * is made in. "The release" is a package whose description is true of it, and a schema
 * component the test registers once the files are in place, as a release that brings a
 * database change does when its own code first runs.
 *
 * @package Corex\Tests\Integration\Releases
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Releases\InstalledRelease;
use Corex\Config\Releases\ReleaseFinisher;
use Corex\Config\Releases\ReleaseInstallation;
use Corex\Config\Releases\ReleaseLeftovers;
use Corex\Config\Releases\ReleaseProgress;
use Corex\Config\Releases\ReleaseRestGateway;
use Corex\Config\Releases\ReleaseReturn;
use Corex\Config\Releases\ReleasesController;
use Corex\Config\Releases\ReleaseStore;
use Corex\Config\Releases\ReleaseSwap;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\SchemaComponent;
use Corex\Database\Schema\SchemaMigrator;
use Corex\Database\Schema\Table;
use Corex\Tests\Support\ReleaseInstaller;
use Corex\Tests\Support\ReleasePackages;

const INSTALLED_VERSION_OPTION = 'corex_release_install_fixture_schema_version';

const THE_PACKAGE = ReleaseInstaller::THE_PACKAGE;

const RUNNING_0_44 = ReleaseInstaller::RUNNING_0_44;

/** The request died: nothing after this line of it ran. */
final class InstallationRequestCutOff extends Error
{
}

/**
 * @param array<string,mixed> $params
 */
function askTheInstaller(string $method, string $route, array $params = []): WP_REST_Response
{
    return ReleaseInstaller::ask($method, $route, $params);
}

/**
 * @return list<string> Each step the installation stood at on the way, once.
 */
function stepUntil(string $step): array
{
    return ReleaseInstaller::stepUntil($step);
}

beforeEach(function () {
    $admins      = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->admin = (int) ($admins[0] ?? 0);
    wp_set_current_user($this->admin);

    $this->table    = 'install_fixture_' . bin2hex(random_bytes(3));
    $this->migrator = new Migrator();
    $this->sites    = [];

    /** A site, with the controller the provider would build for it on the REST server. */
    $this->site = function (array $before = RUNNING_0_44, bool $allowedByConfiguration = false, ?SchemaMigrator $migrator = null): ReleaseInstaller {
        $site          = new ReleaseInstaller($before, $allowedByConfiguration, $migrator);
        $this->sites[] = $site;

        Boot::app()->container()->instance(ReleasesController::class, $site->controller);
        // A server built before this has another controller on these routes.
        $GLOBALS['wp_rest_server'] = null;

        return $site;
    };

    /** What a release registers of the fixture's tables, at a version. */
    $this->registers = function (ReleaseInstaller $site, string $version, array $tables): void {
        $site->registry->register(new SchemaComponent(
            'install-fixture',
            $version,
            array_map(static fn (string $name): Table => (new Table($name))->id()->string('note'), $tables),
            INSTALLED_VERSION_OPTION,
        ));
    };

    $this->given = static function (ReleaseInstaller $site): void {
        $site->giveTheRelease();
    };

    $this->confirmed = static fn (ReleaseInstaller $site): array => $site->confirmTheRelease();

    delete_option(InstalledRelease::OPTION);
    delete_option(INSTALLED_VERSION_OPTION);
});

afterEach(function () {
    Boot::app()->container()->singleton(ReleasesController::class);
    $GLOBALS['wp_rest_server'] = null;

    foreach ($this->sites as $site) {
        $site->copies->discard();
        $site->remove();
    }
    $this->migrator->drop($this->table);
    $this->migrator->drop($this->table . '_more');
    delete_option(InstalledRelease::OPTION);
    delete_option(INSTALLED_VERSION_OPTION);
    remove_all_filters('file_mod_allowed');
});

it('installs a release: every step in order, the folders replaced whole, the table added, and all of it on record', function () {
    global $wpdb;

    $site = ($this->site)();
    // The running release has one table, with a row in it.
    ($this->registers)($site, '1', [$this->table]);
    $this->migrator->create((new Table($this->table))->id()->string('note'));
    update_option(INSTALLED_VERSION_OPTION, '1', false);
    $wpdb->insert($this->migrator->fullName($this->table), ['note' => 'written before the release']);
    ($this->given)($site);

    $prepared = askTheInstaller('POST', '/releases/install/prepare', ['package' => THE_PACKAGE])->get_data()['data'];

    expect($prepared)->toMatchArray([
        'version'  => '0.45.0',
        'replaces' => '0.44.0',
        // The plugin the running release holds and this one does not goes out with it.
        'drops'    => ['wp-content/plugins/corex-legacy'],
    ])
        ->and($prepared['key'])->toMatch('/^[0-9a-f]{64}$/')
        ->and($prepared['recovery_link'])->toBe(ReleaseInstaller::HOME . '/?corex_release_key=' . $prepared['key'] . '&corex_release_recover=1')
        ->and($site->files())->toBe(sortedByPath(RUNNING_0_44));

    // Not before it is confirmed, and not on a version that was not typed.
    expect(askTheInstaller('POST', '/releases/install/step')->get_data()['reason'])->toBe(ReleaseInstallation::NOT_CONFIRMED)
        ->and(askTheInstaller('POST', '/releases/install/confirm', ['phrase' => '0.44.0'])->get_data()['reason'])->toBe(ReleaseInstallation::NOT_CONFIRMED)
        ->and($site->files())->toBe(sortedByPath(RUNNING_0_44));

    expect(askTheInstaller('POST', '/releases/install/confirm', ['phrase' => '0.45.0'])->get_data()['data']['step'])->toBe(ReleaseProgress::UNPACK);

    expect(stepUntil(ReleaseProgress::FINISH))->toBe([
        ReleaseProgress::VERIFY,
        ReleaseProgress::COPY_TABLES,
        ReleaseProgress::SWAP,
        ReleaseProgress::FINISH,
    ]);

    // The files are the release's, and visitors are held off until its database is too.
    expect($site->files())->toBe(sortedByPath([
        'wp-content/plugins/acme-site/acme-site.php' => '<?php // acme 0.45',
        'wp-content/plugins/akismet/akismet.php'     => '<?php // not CoreX',
        'wp-content/themes/corex/style.css'          => '/* 0.45 */',
        'wp-config.php'                              => '<?php // the site\'s own',
        'corex-release.json'                         => RUNNING_0_44['corex-release.json'],
    ]))
        ->and($site->maintenance->isOn())->toBeTrue()
        ->and($site->recovery->isWritten())->toBeTrue()
        ->and($site->installed->version())->toBe('0.44.0');

    // The copy of the table as it was, made before anything moved.
    $copies = $site->copies->kept();
    expect($copies)->toHaveCount(1)
        ->and($copies[0]['table'])->toBe($this->migrator->fullName($this->table))
        ->and($wpdb->get_col('SELECT note FROM `' . $copies[0]['copy'] . '`'))->toBe(['written before the release']);

    // The release's own code runs now, and registers a table the one before did not have.
    ($this->registers)($site, '2', [$this->table, $this->table . '_more']);

    $finished = askTheInstaller('POST', '/releases/install/finish')->get_data()['data'];

    expect($finished)->toMatchArray([
        'done'    => true,
        'version' => '0.45.0',
        'changes' => [['site' => get_current_blog_id(), 'component' => 'install-fixture', 'from' => '1', 'to' => '2']],
    ])
        ->and($this->migrator->exists($this->table . '_more'))->toBeTrue()
        ->and($site->installed->version())->toBe('0.45.0')
        ->and($site->installed->client())->toBe('acme')
        ->and($site->maintenance->isOn())->toBeFalse()
        // The way back stays for as long as there is a release to go back to.
        ->and($site->recovery->isWritten())->toBeTrue()
        ->and(is_file($site->root . '/wp-content/corex-releases/previous/wp-content/plugins/corex-legacy/legacy.php'))->toBeTrue()
        ->and($site->store->state())->toBe([])
        ->and($site->store->packages())->toBe([])
        ->and(glob($site->root . '/wp-content/corex-releases/staging/*'))->toBe([]);

    $logged = $site->store->log();
    expect(end($logged))->toMatchArray(['event' => 'installed', 'version' => '0.45.0', 'from' => '0.44.0', 'by' => $this->admin]);

    // On the activity log too, and the key that opens maintenance and the way back is in neither.
    $events = $site->activity->events;
    expect(array_map(static fn ($event): string => $event->kind, $events))->toBe(['release.installed'])
        ->and($events[0]->actorId)->toBe($this->admin)
        ->and($events[0]->summary['args'])->toBe(['from' => '0.44.0', 'to' => '0.45.0'])
        ->and(wp_json_encode([$events[0]->summary, $events[0]->context, $logged]))->not->toContain($prepared['key']);
});

it('refuses where an installation starts on a site set not to change its files, and starts one where the configuration allows releases', function () {
    add_filter('file_mod_allowed', '__return_false');

    $site = ($this->site)();
    ($this->given)($site);
    $refused = askTheInstaller('POST', '/releases/install/prepare', ['package' => THE_PACKAGE]);

    expect($refused->get_status())->toBe(422)
        ->and($refused->get_data())->toMatchArray([ReleaseRestGateway::MARK => 1, 'ok' => false, 'reason' => ReleaseInstallation::BLOCKED])
        ->and($refused->get_data()['message'])->toContain("define( 'COREX_ALLOW_RELEASE_INSTALL', true );")
        // A package the site may install another day is not thrown away for it.
        ->and(array_column($site->store->packages(), 'name'))->toBe([THE_PACKAGE])
        ->and($site->store->state())->toBe([]);

    $allowed = ($this->site)(RUNNING_0_44, allowedByConfiguration: true);
    ($this->given)($allowed);

    expect(askTheInstaller('POST', '/releases/install/prepare', ['package' => THE_PACKAGE])->get_data()['ok'])->toBeTrue();
});

it('tells a second installation that one is running, and a second request of the same one to wait', function () {
    $site = ($this->site)();
    ($this->confirmed)($site);

    $second = askTheInstaller('POST', '/releases/install/prepare', ['package' => THE_PACKAGE])->get_data();
    expect($second)->toMatchArray(['ok' => false, 'reason' => ReleaseInstallation::IN_PROGRESS]);

    // A request of this installation is still at work: it holds the installer's lock.
    $held = fopen($site->root . '/wp-content/corex-releases/lock', 'c');
    flock($held, LOCK_EX);

    try {
        $beside = askTheInstaller('POST', '/releases/install/step')->get_data();
    } finally {
        flock($held, LOCK_UN);
        fclose($held);
    }

    expect($beside)->toMatchArray(['ok' => false, 'reason' => ReleaseStore::BUSY])
        // And it did nothing: the installation stands where it stood.
        ->and($site->store->state()['step'])->toBe(ReleaseProgress::UNPACK);
});

it('finishes from the screen an installation whose browser went away after the swap, without its key', function () {
    $site = ($this->site)();
    ($this->registers)($site, '1', [$this->table]);
    ($this->confirmed)($site);
    stepUntil(ReleaseProgress::FINISH);

    // The tab is closed. Later the screen is opened, by anybody who may, in a browser that never held the key.
    $standing = askTheInstaller('GET', '/releases')->get_data()['data']['installation'];

    expect($standing)->toMatchArray(['step' => ReleaseProgress::FINISH, 'version' => '0.45.0', 'by' => $this->admin, 'before_the_swap' => false]);

    $finished = askTheInstaller('POST', '/releases/install/finish')->get_data();

    expect($finished['ok'])->toBeTrue()
        ->and($finished['data']['done'])->toBeTrue()
        ->and($site->installed->version())->toBe('0.45.0')
        ->and(askTheInstaller('GET', '/releases')->get_data()['data']['installation'])->toBeNull();
});

it('gives up an installation that has not reached the swap, and will not give up one that has', function () {
    $site = ($this->site)();
    ($this->confirmed)($site);
    askTheInstaller('POST', '/releases/install/step');

    $cancelled = askTheInstaller('POST', '/releases/install/cancel')->get_data();
    $logged    = $site->store->log();

    expect($cancelled['ok'])->toBeTrue()
        ->and($site->store->state())->toBe([])
        ->and(glob($site->root . '/wp-content/corex-releases/staging/*'))->toBe([])
        ->and($site->files())->toBe(sortedByPath(RUNNING_0_44))
        ->and(end($logged))->toMatchArray(['event' => 'cancelled', 'version' => '0.45.0', 'by' => $this->admin]);

    // Past the swap the site is the new release: the way out of that is going back.
    $swapped = ($this->site)();
    ($this->confirmed)($swapped);
    stepUntil(ReleaseProgress::FINISH);

    expect(askTheInstaller('POST', '/releases/install/cancel')->get_data())->toMatchArray(['ok' => false, 'reason' => ReleaseInstallation::PAST_CANCELLING])
        ->and($swapped->store->state()['step'])->toBe(ReleaseProgress::FINISH);
});

it('names a database change that failed, does not record the release, and leaves visitors held off', function () {
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
    $site = ($this->site)(RUNNING_0_44, migrator: $willNotCreate);
    ($this->confirmed)($site);
    stepUntil(ReleaseProgress::FINISH);
    ($this->registers)($site, '2', [$this->table]);

    $failed = askTheInstaller('POST', '/releases/install/finish');
    $logged = $site->store->log();

    expect($failed->get_status())->toBe(422)
        ->and($failed->get_data())->toMatchArray(['ok' => false, 'reason' => ReleaseFinisher::FAILED])
        ->and($failed->get_data()['message'])->toContain('install-fixture')
        ->and($site->installed->version())->toBe('0.44.0')
        ->and($site->maintenance->isOn())->toBeTrue()
        ->and(end($logged))->toMatchArray(['event' => 'failed', 'version' => '0.45.0', 'reason' => ReleaseFinisher::FAILED])
        // The screen, opened again, says where the installation stands and why.
        ->and(askTheInstaller('GET', '/releases')->get_data()['data']['installation']['failed'])->toContain('install-fixture');
});

it('asks twice for an older release, and for the client by name on a site that does not know whose it is', function () {
    $newer = RUNNING_0_44;
    $newer['corex-release.json'] = '{"corex_version":"0.46.0","client":"acme"}';
    $site = ($this->site)($newer);
    ($this->given)($site);

    $prepared = askTheInstaller('POST', '/releases/install/prepare', ['package' => THE_PACKAGE])->get_data()['data'];

    expect($prepared['older'])->toBeTrue()
        ->and(askTheInstaller('POST', '/releases/install/confirm', ['phrase' => '0.45.0'])->get_data())->toMatchArray(['ok' => false, 'reason' => ReleaseInstallation::NOT_CONFIRMED])
        ->and(askTheInstaller('POST', '/releases/install/confirm', ['phrase' => '0.45.0', 'older' => true])->get_data()['ok'])->toBeTrue();

    $unknown = RUNNING_0_44;
    unset($unknown['corex-release.json']);
    $site = ($this->site)($unknown);
    ($this->given)($site);

    $prepared = askTheInstaller('POST', '/releases/install/prepare', ['package' => THE_PACKAGE])->get_data()['data'];

    expect($prepared['client_unconfirmed'])->toBeTrue()
        ->and(askTheInstaller('POST', '/releases/install/confirm', ['phrase' => '0.45.0', 'client' => 'globex'])->get_data())->toMatchArray(['ok' => false, 'reason' => ReleaseInstallation::NOT_CONFIRMED])
        ->and(askTheInstaller('POST', '/releases/install/confirm', ['phrase' => '0.45.0', 'client' => 'acme'])->get_data()['ok'])->toBeTrue();
});

it('names a plugin the package would put back to an older version, and installs it only when that is said', function () {
    $updated = RUNNING_0_44;
    $updated['wp-content/plugins/acme-site/acme-site.php'] = "<?php
/*
 * Plugin Name: Acme Site
 * Version: 2.1.0
 */";
    $site = ($this->site)($updated);
    $site->give(ReleasePackages::faithful(
        [...ReleaseInstaller::RELEASE_0_45, 'wp-content/plugins/acme-site/acme-site.php' => "<?php
/*
 * Plugin Name: Acme Site
 * Version: 2.0.0
 */"],
        [],
        ['corex_version' => '0.45.0'],
    ), THE_PACKAGE);

    $inspected = askTheInstaller('POST', '/releases/inspect', ['package' => THE_PACKAGE])->get_data()['data'];
    $prepared  = askTheInstaller('POST', '/releases/install/prepare', ['package' => THE_PACKAGE])->get_data()['data'];
    $downgrade = [['path' => 'wp-content/plugins/acme-site', 'name' => 'Acme Site', 'site' => '2.1.0', 'package' => '2.0.0']];

    // Said with what the package is, before anything is typed, and again where it is confirmed.
    expect($inspected['downgrades'])->toBe($downgrade)
        ->and($prepared['downgrades'])->toBe($downgrade)
        ->and(askTheInstaller('POST', '/releases/install/confirm', ['phrase' => '0.45.0'])->get_data())->toMatchArray(['ok' => false, 'reason' => ReleaseInstallation::NOT_CONFIRMED])
        ->and(ReleaseProgress::standing($site->store)?->step)->toBe(ReleaseProgress::AWAITING)
        ->and(askTheInstaller('POST', '/releases/install/confirm', ['phrase' => '0.45.0', 'downgrade' => true])->get_data()['ok'])->toBeTrue();
});

it('leaves the site as it was when a folder will not move, and says the release was not installed', function () {
    $site = ($this->site)();
    $site->rename = static fn (string $from, string $to): bool => ! str_contains(str_replace('\\', '/', $to), 'plugins/acme-site') && rename($from, $to);
    ($this->confirmed)($site);
    stepUntil(ReleaseProgress::SWAP);

    $refused = askTheInstaller('POST', '/releases/install/step');
    $logged  = $site->store->log();

    expect($refused->get_status())->toBe(422)
        ->and($refused->get_data())->toMatchArray(['ok' => false, 'reason' => ReleaseSwap::FAILED])
        ->and($site->files())->toBe(sortedByPath(RUNNING_0_44))
        ->and($site->maintenance->isOn())->toBeFalse()
        ->and($site->recovery->isWritten())->toBeFalse()
        ->and($site->store->state())->toBe([])
        ->and(end($logged))->toMatchArray(['event' => 'failed', 'version' => '0.45.0', 'reason' => ReleaseSwap::FAILED]);
});

it('finishes a swap its request was cut off in the middle of, when the next request comes', function () {
    $site = ($this->site)();
    ($this->confirmed)($site);
    stepUntil(ReleaseProgress::SWAP);

    $renames      = 0;
    $site->rename = static function (string $from, string $to) use (&$renames): bool {
        if (++$renames === 3) {
            throw new InstallationRequestCutOff();
        }

        return rename($from, $to);
    };

    expect(fn () => askTheInstaller('POST', '/releases/install/step'))->toThrow(InstallationRequestCutOff::class);

    // Part one release and part the other, and nobody is served it.
    expect($site->maintenance->isOn())->toBeTrue()
        ->and($site->store->state()['step'])->toBe(ReleaseProgress::SWAP);

    $site->rename = null;

    expect(stepUntil(ReleaseProgress::FINISH))->toBe([ReleaseProgress::FINISH])
        ->and($site->files()['wp-content/plugins/acme-site/acme-site.php'])->toBe('<?php // acme 0.45')
        ->and($site->files()['wp-content/themes/corex/style.css'])->toBe('/* 0.45 */')
        ->and(isset($site->files()['wp-content/plugins/corex-legacy/legacy.php']))->toBeFalse();
});

it('gives up an installation whose swap was cut off as its first folder was to move, and leaves the site as it was', function () {
    $site = ($this->site)();
    cutTheSwapOffAfter($site, 0);

    $standing  = askTheInstaller('GET', '/releases')->get_data()['data']['installation'];
    $cancelled = askTheInstaller('POST', '/releases/install/cancel')->get_data();
    $logged    = $site->store->log();

    expect($standing)->toMatchArray(['step' => ReleaseProgress::SWAP, 'moved_nothing' => true])
        ->and($cancelled['ok'])->toBeTrue()
        ->and($site->files())->toBe(sortedByPath(RUNNING_0_44))
        ->and($site->maintenance->isOn())->toBeFalse()
        ->and($site->recovery->isWritten())->toBeFalse()
        ->and($site->store->state())->toBe([])
        ->and(glob($site->root . '/wp-content/corex-releases/staging/*'))->toBe([])
        ->and(end($logged))->toMatchArray(['event' => 'cancelled', 'version' => '0.45.0', 'by' => $this->admin])
        ->and(askTheInstaller('GET', '/releases')->get_data()['data'])->toMatchArray(['installation' => null, 'leftovers' => []]);
});

it('will not give up an installation whose swap moved a folder before it was cut off', function () {
    $site = ($this->site)();
    cutTheSwapOffAfter($site, 2);

    $standing = askTheInstaller('GET', '/releases')->get_data()['data']['installation'];

    expect($standing)->toMatchArray(['step' => ReleaseProgress::SWAP, 'moved_nothing' => false])
        ->and(askTheInstaller('POST', '/releases/install/cancel')->get_data())->toMatchArray(['ok' => false, 'reason' => ReleaseInstallation::PAST_CANCELLING])
        ->and($site->store->state()['step'])->toBe(ReleaseProgress::SWAP)
        ->and($site->maintenance->isOn())->toBeTrue();
});

it('gives up an installation cut off before it set the kept release aside, which stays the one to go back to', function () {
    $site = ($this->site)();
    $site->installTheRelease();
    $site->giveTheRelease(['corex_version' => '0.45.1']);
    $prepared = askTheInstaller('POST', '/releases/install/prepare', ['package' => THE_PACKAGE])->get_data()['data'];
    askTheInstaller('POST', '/releases/install/confirm', ['phrase' => '0.45.1']);
    stepUntil(ReleaseProgress::SWAP);

    // Perego, 2026-10-18: the swap request held visitors off and was cut off before anything
    // moved. Its safeguards are up; the release 0.45.0 replaced is still kept, with its journal.
    $keyHash = hash('sha256', $prepared['key']);
    $site->recovery->write($keyHash);
    $site->maintenance->begin($keyHash, time());

    $refused   = askTheInstaller('POST', '/releases/return', ['phrase' => '0.44.0'])->get_data();
    $cancelled = askTheInstaller('POST', '/releases/install/cancel')->get_data();
    $opened    = askTheInstaller('GET', '/releases')->get_data()['data'];

    expect($refused)->toMatchArray(['ok' => false, 'reason' => ReleaseReturn::INSTALLING])
        ->and($cancelled['ok'])->toBeTrue()
        ->and($site->files()['wp-content/plugins/acme-site/acme-site.php'])->toBe('<?php // acme 0.45')
        ->and($site->maintenance->isOn())->toBeFalse()
        ->and($site->recovery->isWritten())->toBeFalse()
        ->and($opened['installation'])->toBeNull()
        ->and($opened['leftovers'])->toBe([])
        ->and($opened['previous'])->toMatchArray(['status' => 'kept', 'version' => '0.44.0', 'replaced_by' => '0.45.0']);
});

it('names what an installation left when none is in progress, and removes it when asked, changing nothing of the release', function () {
    $site = ($this->site)();
    $site->installTheRelease();

    // The way back to the kept release is not left over: it is the kept release's.
    expect(askTheInstaller('GET', '/releases')->get_data()['data']['leftovers'])->toBe([]);

    // What Perego's host held after its installation's record was gone.
    $releases = $site->root . '/wp-content/corex-releases';
    mkdir($releases . '/staging/wp-content/plugins/acme-site', 0777, true);
    file_put_contents($releases . '/staging/wp-content/plugins/acme-site/acme-site.php', '<?php // unpacked, never installed');
    unlink($site->journal->file());
    $site->recovery->write(hash('sha256', 'a key no installation holds'));
    $running = $site->files();

    $found = askTheInstaller('GET', '/releases')->get_data()['data']['leftovers'];

    expect(array_column($found, 'what'))->toBe([ReleaseLeftovers::STAGING, ReleaseLeftovers::RECOVERY_FILE, ReleaseLeftovers::PREVIOUS_UNKNOWN])
        ->and(array_column($found, 'where'))->each->toBeString()->not->toBeEmpty();

    $removed = askTheInstaller('POST', '/releases/leftovers/remove')->get_data();

    expect($removed['ok'])->toBeTrue()
        ->and($removed['data']['removed'])->toBe($found)
        ->and($site->recovery->isWritten())->toBeFalse()
        ->and(glob($releases . '/staging/*'))->toBe([])
        ->and(glob($releases . '/previous/*'))->toBe([])
        ->and($site->files())->toBe($running)
        ->and(askTheInstaller('GET', '/releases')->get_data()['data']['leftovers'])->toBe([]);
});

it('removes nothing while an installation is in progress: what it holds is not left over', function () {
    $site = ($this->site)();
    ($this->confirmed)($site);
    askTheInstaller('POST', '/releases/install/step');

    expect(askTheInstaller('GET', '/releases')->get_data()['data']['leftovers'])->toBe([])
        ->and(askTheInstaller('POST', '/releases/leftovers/remove')->get_data())->toMatchArray(['ok' => false, 'reason' => ReleaseInstallation::IN_PROGRESS])
        ->and(glob($site->root . '/wp-content/corex-releases/staging/*'))->not->toBe([]);
});

/**
 * Confirm the fixture release, step it to the swap, and have the swap's request die as it is
 * about to make a rename: after the number of renames given.
 */
function cutTheSwapOffAfter(ReleaseInstaller $site, int $renames): void
{
    $site->confirmTheRelease();
    stepUntil(ReleaseProgress::SWAP);

    $done         = 0;
    $site->rename = static function (string $from, string $to) use (&$done, $renames): bool {
        if ($done++ === $renames) {
            throw new InstallationRequestCutOff();
        }

        return rename($from, $to);
    };

    try {
        askTheInstaller('POST', '/releases/install/step');

        throw new RuntimeException('The swap request was not cut off.');
    } catch (InstallationRequestCutOff) {
        // It died there: nothing after the rename it was making ran.
    } finally {
        $site->rename = null;
    }
}

/**
 * @param array<string,string> $files
 *
 * @return array<string,string>
 */
function sortedByPath(array $files): array
{
    ksort($files);

    return $files;
}
