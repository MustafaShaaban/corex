<?php

/**
 * The release installer as a site builds it, over a site in the temp folder (spec 107).
 *
 * The folders are the test's own, so an installation moves nothing of the site the tests run
 * on. WordPress is the real one: its REST server, its users, its options, its database. The
 * schema registry is the test's too, so a test says what "the release" registers, and can say
 * something else after the swap, as a release that brings a database change does.
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Closure;
use Corex\Activity\ActivityService;
use Corex\Boot;
use Corex\Config\Releases\InstalledRelease;
use Corex\Config\Releases\ReleaseCaller;
use Corex\Config\Releases\ReleaseCompletion;
use Corex\Config\Releases\ReleaseContentHash;
use Corex\Config\Releases\ReleaseDesk;
use Corex\Config\Releases\ReleaseDowngrades;
use Corex\Config\Releases\ReleaseFinisher;
use Corex\Config\Releases\ReleaseHostFacts;
use Corex\Config\Releases\ReleaseInstallation;
use Corex\Config\Releases\ReleaseJournal;
use Corex\Config\Releases\ReleaseLeftovers;
use Corex\Config\Releases\ReleaseMaintenance;
use Corex\Config\Releases\ReleaseOverview;
use Corex\Config\Releases\ReleasePackageInspector;
use Corex\Config\Releases\ReleaseProgress;
use Corex\Config\Releases\ReleaseRecord;
use Corex\Config\Releases\ReleaseRecoveryPlugin;
use Corex\Config\Releases\ReleaseRestGateway;
use Corex\Config\Releases\ReleaseReturn;
use Corex\Config\Releases\ReleaseSafeguards;
use Corex\Config\Releases\ReleasesController;
use Corex\Config\Releases\ReleaseStaging;
use Corex\Config\Releases\ReleaseSteps;
use Corex\Config\Releases\ReleaseStore;
use Corex\Config\Releases\ReleaseSwap;
use Corex\Config\Releases\ReleaseTableCopies;
use Corex\Config\Releases\ReleaseUpkeep;
use Corex\Config\Releases\ReleaseUpload;
use Corex\Config\Releases\WpReleaseTableCopier;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\SchemaMigrator;
use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\SchemaVersionStore;
use Corex\Database\Schema\SiteMigrationRunner;
use Corex\Events\EventDispatcher;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\NetworkContext;
use Corex\Multisite\SiteScope;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

final class ReleaseInstaller
{
    public const HOME = 'https://acme.test';

    /** The name the fixture release's package goes by on a site. */
    public const THE_PACKAGE = 'corex-release-acme-0.45.0-20261011-120000.zip';

    /** A site as it runs 0.44.0: extracted by hand, with a file no release holds any more. */
    public const RUNNING_0_44 = [
        'wp-content/plugins/acme-site/acme-site.php'   => '<?php // acme 0.44',
        'wp-content/plugins/acme-site/src/Removed.php' => '<?php // left by an earlier release',
        'wp-content/plugins/corex-legacy/legacy.php'   => '<?php // 0.45 does not hold this plugin',
        'wp-content/plugins/akismet/akismet.php'       => '<?php // not CoreX',
        'wp-content/themes/corex/style.css'            => '/* 0.44 */',
        'wp-config.php'                                => '<?php // the site\'s own',
        'corex-release.json'                           => '{"corex_version":"0.44.0","client":"acme","release_paths":["wp-content/plugins/acme-site","wp-content/plugins/corex-legacy","wp-content/themes/corex"]}',
    ];

    public const RELEASE_0_45 = [
        'wp-content/plugins/acme-site/acme-site.php' => '<?php // acme 0.45',
        'wp-content/themes/corex/style.css'          => '/* 0.45 */',
    ];

    /** What the installer writes for itself, which is not the site: its place, its two files. */
    private const THE_INSTALLERS = ['wp-content/corex-releases/', 'wp-content/mu-plugins/corex-release-recovery.php', '.maintenance'];

    public readonly string $root;
    public readonly ReleaseStore $store;
    public readonly InstalledRelease $installed;
    public readonly ReleaseJournal $journal;
    public readonly ReleaseMaintenance $maintenance;
    public readonly ReleaseRecoveryPlugin $recovery;
    public readonly ReleaseTableCopies $copies;
    public readonly SchemaRegistry $registry;
    public readonly RecordingActivityRepository $activity;
    public readonly ReleasesController $controller;

    /** Moves a folder in place of PHP's own, where a test needs one not to move or the request to die. */
    public ?Closure $rename = null;

    /**
     * Whether `ask()` signs its requests as the screen does, with the nonce. A test of the
     * command that installs with an Application Password turns it off, and back on after.
     */
    public static bool $signedByTheScreen = true;

    /**
     * @param array<string,string> $before                 Every file on the site before, by path, with what it holds.
     * @param bool                 $allowedByConfiguration What the site's configuration says of installing releases.
     * @param SchemaMigrator|null  $migrator               What makes the release's tables; the site's own, unless a test needs one to fail.
     * @param bool                 $pushAllowed            Whether the site's configuration lets an Application Password install a
     *                                                     release (`ReleaseCaller::PUSH_ALLOWED_BY`).
     */
    public function __construct(array $before, bool $allowedByConfiguration = false, ?SchemaMigrator $migrator = null, bool $pushAllowed = false)
    {
        $this->root = sys_get_temp_dir() . '/corex-site-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/wp-content', 0777, true);
        self::write($this->root, $before);

        $container = Boot::app()->container();
        $root      = $this->root;
        $content   = $this->root . '/wp-content';
        $migrator ??= new Migrator();

        $this->store       = new ReleaseStore($content);
        $this->installed   = new InstalledRelease($this->root);
        $this->journal     = new ReleaseJournal($this->store);
        $this->maintenance = new ReleaseMaintenance($this->root);
        $this->recovery    = new ReleaseRecoveryPlugin($content . '/mu-plugins', $this->journal, $this->maintenance);
        $this->registry    = new SchemaRegistry();
        $this->copies      = new ReleaseTableCopies(new WpReleaseTableCopier(), $this->registry, new Migrator());
        $this->activity    = new RecordingActivityRepository();

        $staging    = new ReleaseStaging($this->store, new ReleaseContentHash());
        $swap       = new ReleaseSwap(
            $this->store,
            $staging,
            $this->journal,
            $this->root,
            fn (string $from, string $to): bool => $this->rename === null ? @rename($from, $to) : ($this->rename)($from, $to),
        );
        $safeguards = new ReleaseSafeguards($this->maintenance, $this->recovery, self::HOME);
        $caller     = new ReleaseCaller($pushAllowed);
        $record     = new ReleaseRecord($this->store, new ActivityService($this->activity), static fn (int $id): string => 'User ' . $id, $caller->applicationPassword(...));
        $desk       = new ReleaseDesk(
            $this->store,
            new ReleasePackageInspector($this->installed, new ReleaseDowngrades($this->root), PHP_VERSION, '7.1.3'),
            $this->installed,
            static fn (): ReleaseHostFacts => ReleaseHostFacts::ofThisHost(
                [$content, $content . '/plugins', $content . '/themes', $content . '/mu-plugins', $root],
                $allowedByConfiguration,
            ),
        );
        $steps        = new ReleaseSteps($this->store, $staging, $this->copies, $swap, $this->journal, $safeguards, $this->installed);
        $installation = new ReleaseInstallation($this->store, $desk, $this->installed, $steps, $safeguards, $record, $this->root);
        $leftovers    = new ReleaseLeftovers($this->store, $staging, $this->journal, $this->recovery);
        $return       = new ReleaseReturn($this->store, $swap, $this->journal, $staging, $safeguards, $this->installed, $this->copies, $record, $steps);
        $versions     = $container->make(SchemaVersionStore::class);
        $scope        = $container->make(SiteScope::class);
        $network      = $container->make(NetworkContext::class);

        $this->controller = new ReleasesController(
            new ReleaseRestGateway($caller),
            $desk,
            new ReleaseUpload($this->store),
            $installation,
            new ReleaseCompletion(
                $this->store,
                new ReleaseFinisher(
                    new SiteMigrationRunner($migrator, $this->registry, $versions, $scope, $network, $container->make(EventDispatcher::class)),
                    $this->registry,
                    $versions,
                    $scope,
                    $network,
                    $container->make(MultisiteContext::class),
                ),
                $this->installed,
                $staging,
                $safeguards,
                $this->journal,
                $record,
            ),
            new ReleaseOverview($desk, new ReleaseUpkeep($this->store, $return, $this->copies), $return, $record, $installation, $leftovers),
            $return,
            $leftovers,
        );
    }

    /** Put a package where a site keeps the ones it is given, under a name. */
    public function give(string $zipFile, string $name): void
    {
        copy($zipFile, $this->store->fileIn('incoming', $name));
    }

    /**
     * Give the site the fixture release: 0.45.0 for acme, unless the description is changed.
     *
     * @param array<string,mixed> $changes Changes to the package's description.
     */
    public function giveTheRelease(array $changes = ['corex_version' => '0.45.0']): void
    {
        $this->give(
            ReleasePackages::faithful(self::RELEASE_0_45, ['wp-includes/version.php' => '<?php // WordPress, which is not replaced'], $changes),
            self::THE_PACKAGE,
        );
    }

    /**
     * Ask one of the Releases routes, as the screen asks: signed, through the REST server.
     *
     * @param array<string,mixed> $params
     */
    public static function ask(string $method, string $route, array $params = []): WP_REST_Response
    {
        $request = new WP_REST_Request($method, '/corex/v1' . $route);
        if (self::$signedByTheScreen) {
            $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        }
        $method === 'GET' ? $request->set_query_params($params) : $request->set_body_params($params);

        return rest_get_server()->dispatch($request);
    }

    /**
     * Ask for the next step until the installation stands at the one given.
     *
     * @return list<string> Each step it stood at on the way, once.
     */
    public static function stepUntil(string $step): array
    {
        $stoodAt = [];

        for ($asked = 0; $asked < 40; $asked++) {
            $answer = self::ask('POST', '/releases/install/step')->get_data();

            if (($answer['ok'] ?? false) !== true) {
                throw new RuntimeException('The installation stopped: ' . wp_json_encode($answer));
            }

            $at = $answer['data']['step'];
            if (end($stoodAt) !== $at) {
                $stoodAt[] = $at;
            }
            if ($at === $step) {
                return $stoodAt;
            }
        }

        throw new RuntimeException('The installation did not reach ' . $step . ': ' . implode(', ', $stoodAt));
    }

    /**
     * Give the fixture release, prepare it and confirm it.
     *
     * @return array<string,mixed> What `prepare` answered: the statement, the key, the recovery link.
     */
    public function confirmTheRelease(): array
    {
        $this->giveTheRelease();
        $prepared = self::ask('POST', '/releases/install/prepare', ['package' => self::THE_PACKAGE])->get_data()['data'];
        self::ask('POST', '/releases/install/confirm', ['phrase' => '0.45.0']);

        return $prepared;
    }

    /**
     * Install the fixture release, from the package to the release being recorded.
     *
     * @return array<string,mixed> What `prepare` answered.
     */
    public function installTheRelease(): array
    {
        $prepared = $this->confirmTheRelease();
        self::stepUntil(ReleaseProgress::FINISH);
        self::ask('POST', '/releases/install/finish');

        return $prepared;
    }

    /**
     * Every file of the site that is not the installer's own, by path, with what it holds.
     *
     * @return array<string,string>
     */
    public function files(): array
    {
        $files   = [];
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS));

        foreach ($entries as $entry) {
            $path = str_replace('\\', '/', substr($entry->getPathname(), strlen($this->root) + 1));

            if ($entry->isFile() && ! self::isTheInstallers($path)) {
                $files[$path] = (string) file_get_contents($entry->getPathname());
            }
        }
        ksort($files);

        return $files;
    }

    public function remove(): void
    {
        self::delete($this->root);
        ReleasePackages::clean();
    }

    /**
     * @param array<string,string> $files
     */
    private static function write(string $root, array $files): void
    {
        foreach ($files as $path => $holds) {
            if (! is_dir(dirname("$root/$path"))) {
                mkdir(dirname("$root/$path"), 0777, true);
            }
            file_put_contents("$root/$path", $holds);
        }
    }

    private static function isTheInstallers(string $path): bool
    {
        foreach (self::THE_INSTALLERS as $own) {
            if (str_starts_with($path, $own)) {
                return true;
            }
        }

        return false;
    }

    private static function delete(string $path): void
    {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            is_dir("$path/$entry") ? self::delete("$path/$entry") : unlink("$path/$entry");
        }
        rmdir($path);
    }
}
