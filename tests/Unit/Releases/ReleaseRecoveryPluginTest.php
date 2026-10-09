<?php

/**
 * Unit test: the way back that needs no admin (spec 107, plan D9; FR-042).
 *
 * When an installation starts, CoreX writes a must-use plugin that uses nothing of CoreX.
 * WordPress loads it before any ordinary plugin, so it runs when the release just installed
 * cannot. Asked with the installation's key, it puts every folder back and takes the site out
 * of maintenance.
 *
 * It is run here as WordPress would run it and CoreX would not be there to help: in a PHP
 * process of its own, with `ABSPATH` defined and nothing else loaded.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\ReleaseHostFacts;
use Corex\Config\Releases\ReleaseJournal;
use Corex\Config\Releases\ReleaseMaintenance;
use Corex\Config\Releases\ReleaseRecoveryPlugin;
use Corex\Config\Releases\ReleaseRefused;
use Corex\Tests\Support\ReleaseSite;

const RECOVERY_KEY = '0a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f9';

const RUNNING_BEFORE = [
    'wp-content/plugins/corex-core/corex-core.php' => '<?php // core 0.43',
    'wp-content/plugins/corex-legacy/legacy.php'   => '<?php // dropped by 0.44',
    'wp-content/plugins/akismet/akismet.php'       => '<?php // not CoreX',
    'wp-content/themes/corex/style.css'            => '/* 0.43 */',
];

const INSTALLED_OVER_IT = [
    'wp-content/plugins/corex-core/corex-core.php' => '<?php // core 0.44, and it does not boot',
    'wp-content/plugins/acme-site/acme-site.php'   => '<?php // acme 0.44',
    'wp-content/themes/corex/style.css'            => '/* 0.44 */',
];

/**
 * What a visitor's browser gets from a WordPress that loads this must-use plugin and then goes
 * on, with nothing of CoreX loaded.
 *
 * @param array<string,string> $query The request's query.
 */
function askWordPressWith(string $mustUsePlugin, array $query): string
{
    $runner = tempnam(sys_get_temp_dir(), 'corex-wp-') . '.php';
    file_put_contents($runner, '<?php define("ABSPATH", __DIR__ . "/"); $_GET = ' . var_export($query, true) . '; '
        . 'include ' . var_export($mustUsePlugin, true) . '; echo "<<WordPress went on to load the site>>";');

    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner), $output);
    unlink($runner);
    @unlink(substr($runner, 0, -4));

    return implode("\n", $output);
}

function sortedByPath(array $files): array
{
    ksort($files);

    return $files;
}

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('wp_mkdir_p')->alias(static fn (string $path): bool => is_dir($path) || mkdir($path, 0777, true));
    Functions\when('wp_json_encode')->alias('json_encode');

    // A site on which 0.44 has just been put in place, under maintenance.
    $this->site        = new ReleaseSite(RUNNING_BEFORE, INSTALLED_OVER_IT);
    $this->maintenance = new ReleaseMaintenance($this->site->root);
    $this->recovery    = new ReleaseRecoveryPlugin($this->site->root . '/wp-content/mu-plugins', $this->site->journal, $this->maintenance);
    $this->plugin      = $this->site->root . '/wp-content/mu-plugins/corex-release-recovery.php';

    $this->recovery->write(hash('sha256', RECOVERY_KEY));
    $this->maintenance->begin(hash('sha256', RECOVERY_KEY), time());
    $this->site->swap()->swap($this->site->manifest, ['wp-content/plugins/corex-core', 'wp-content/plugins/corex-legacy', 'wp-content/themes/corex']);
});

afterEach(function () {
    $this->site->remove();
});

it('puts every folder back and takes the site out of maintenance, with the key and without CoreX', function () {
    $answer = askWordPressWith($this->plugin, ['corex_release_key' => RECOVERY_KEY, 'corex_release_recover' => '1']);

    expect($this->site->files())->toBe(sortedByPath(RUNNING_BEFORE))
        ->and($this->maintenance->isOn())->toBeFalse()
        ->and($this->site->journal->status())->toBe(ReleaseJournal::UNDONE)
        // It says what it did, in plain text, and what it did not do.
        ->and($answer)->toContain('Put back: ' . $this->site->root . '/wp-content/plugins/corex-core')
        ->and($answer)->toContain('No change to the database was reversed.')
        // And the request ends there: WordPress does not go on to load plugins from folders
        // that have just been moved.
        ->and($answer)->not->toContain('WordPress went on');
});

it('does nothing and says nothing to a request that does not carry the key', function (array $query) {
    $after = $this->site->files();

    $answer = askWordPressWith($this->plugin, $query);

    expect($answer)->toBe('<<WordPress went on to load the site>>')
        ->and($this->site->files())->toBe($after)
        ->and($this->maintenance->isOn())->toBeTrue()
        ->and($this->site->journal->status())->toBe(ReleaseJournal::SWAPPED);
})->with([
    'an ordinary request'                    => [[]],
    'a wrong key'                            => [['corex_release_key' => str_repeat('0', 64), 'corex_release_recover' => '1']],
    'recovery asked for with no key'         => [['corex_release_recover' => '1']],
    'the key, with recovery not asked for'   => [['corex_release_key' => RECOVERY_KEY]],
]);

it('has nothing left to do when it is asked a second time, and says so', function () {
    askWordPressWith($this->plugin, ['corex_release_key' => RECOVERY_KEY, 'corex_release_recover' => '1']);

    $again = askWordPressWith($this->plugin, ['corex_release_key' => RECOVERY_KEY, 'corex_release_recover' => '1']);

    expect($again)->toContain('Nothing needed putting back.')
        ->and($this->site->files())->toBe(sortedByPath(RUNNING_BEFORE));
});

it('holds the key\'s hash and never the key', function () {
    $written = (string) file_get_contents($this->plugin);

    expect($written)->toContain(hash('sha256', RECOVERY_KEY))
        ->and($written)->not->toContain(RECOVERY_KEY);
});

it('gives the address that asks for it', function () {
    expect($this->recovery->link('https://acme.test/', RECOVERY_KEY))
        ->toBe('https://acme.test/?corex_release_key=' . RECOVERY_KEY . '&corex_release_recover=1');
});

it('is removed when there is no release to go back to', function () {
    $this->recovery->remove();
    $this->recovery->remove();

    expect($this->recovery->isWritten())->toBeFalse()
        ->and(is_file($this->plugin))->toBeFalse();
});

it('refuses in words when it cannot be written: an installation does not start without its way back', function () {
    $this->recovery->remove();
    Functions\when('wp_mkdir_p')->justReturn(false);
    $nowhere = new ReleaseRecoveryPlugin($this->site->root . '/wp-content/no-such-folder', $this->site->journal, $this->maintenance);

    try {
        $nowhere->write(hash('sha256', RECOVERY_KEY));
    } catch (ReleaseRefused $refused) {
        expect($refused->reason)->toBe(ReleaseHostFacts::NOT_WRITABLE)
            ->and($refused->getMessage())->toContain('no-such-folder');
    }

    expect(isset($refused))->toBeTrue();
});
