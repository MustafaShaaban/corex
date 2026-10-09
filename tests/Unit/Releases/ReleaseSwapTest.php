<?php

/**
 * Unit test: a release put in the place of the one that is running, by renames that are written
 * down first (spec 107, plan D6; FR-020, FR-022, FR-026).
 *
 * This is the step that changes a site. Each folder of the running release is moved out to
 * `previous/` and the staged one moved in. What is about to be done is written in a journal
 * before it is done and marked after, so a request that is cut off between two renames is
 * finished or undone by the next one, which says which.
 *
 * Real folders in the temp directory. The one thing replaced is the rename itself, where a test
 * needs it to fail or the request to die at a chosen moment.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\ReleaseContentHash;
use Corex\Config\Releases\ReleaseJournal;
use Corex\Config\Releases\ReleaseManifest;
use Corex\Config\Releases\ReleaseRefused;
use Corex\Config\Releases\ReleaseStaging;
use Corex\Config\Releases\ReleaseStore;
use Corex\Config\Releases\ReleaseSwap;
use Corex\Tests\Support\ReleasePackages;

/** The request died: nothing after this line of the swap ran. */
final class RequestCutOff extends Error
{
}

const THE_NEW_RELEASE = [
    'wp-content/plugins/corex-core/corex-core.php' => '<?php // core 0.44',
    'wp-content/plugins/acme-site/acme-site.php'   => '<?php // acme 0.44',
    'wp-content/themes/corex/style.css'            => '/* 0.44 */',
    'wp-content/vendor/autoload.php'               => '<?php // autoload 0.44',
];

/** What is on the site before: the running release, a folder it will drop, and one that is nobody's. */
const THE_SITE_BEFORE = [
    'wp-content/plugins/corex-core/corex-core.php'   => '<?php // core 0.43',
    'wp-content/plugins/corex-core/removed-file.php' => '<?php // gone in 0.44',
    'wp-content/plugins/corex-legacy/legacy.php'     => '<?php // dropped by 0.44',
    'wp-content/plugins/akismet/akismet.php'         => '<?php // not CoreX',
    'wp-content/themes/corex/style.css'              => '/* 0.43 */',
    'wp-content/vendor/autoload.php'                 => '<?php // autoload 0.43',
    'wp-content/uploads/2026/photo.jpg'              => 'a photo',
];

/** The folders the running release recorded as its own. `acme-site` is new in 0.44. */
const OWNED_BEFORE = [
    'wp-content/plugins/corex-core',
    'wp-content/plugins/corex-legacy',
    'wp-content/themes/corex',
    'wp-content/vendor',
];

function writeFiles(string $root, array $files): void
{
    foreach ($files as $path => $holds) {
        if (! is_dir(dirname("$root/$path"))) {
            mkdir(dirname("$root/$path"), 0777, true);
        }
        file_put_contents("$root/$path", $holds);
    }
}

/** Every file of the site that is not the installer's own, by path, with what it holds. */
function theSite(string $root): array
{
    $files   = [];
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($entries as $entry) {
        $path = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
        if (! str_starts_with($path, 'wp-content/corex-releases/')) {
            $files[$path] = (string) file_get_contents($entry->getPathname());
        }
    }
    ksort($files);

    return $files;
}

/** The site as it is once 0.44 is in: the release's folders replaced, the dropped one gone, the rest as it was. */
function theSiteAfter(): array
{
    $after = array_merge(
        array_filter(
            THE_SITE_BEFORE,
            static fn (string $path): bool => str_starts_with($path, 'wp-content/plugins/akismet/') || str_starts_with($path, 'wp-content/uploads/'),
            ARRAY_FILTER_USE_KEY,
        ),
        THE_NEW_RELEASE,
    );
    ksort($after);

    return $after;
}

function sorted(array $files): array
{
    ksort($files);

    return $files;
}

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('wp_mkdir_p')->alias(static fn (string $path): bool => is_dir($path) || mkdir($path, 0777, true));
    Functions\when('wp_json_encode')->alias('json_encode');

    $this->site = sys_get_temp_dir() . '/corex-site-' . bin2hex(random_bytes(4));
    mkdir($this->site);
    writeFiles($this->site, THE_SITE_BEFORE);

    $this->store   = new ReleaseStore($this->site . '/wp-content');
    $this->staging = new ReleaseStaging($this->store, new ReleaseContentHash());
    $this->journal = new ReleaseJournal($this->store);

    // The release, unpacked and waiting.
    $package = ReleasePackages::faithful(THE_NEW_RELEASE);
    $zip     = new ZipArchive();
    $zip->open($package);
    $this->manifest = ReleaseManifest::fromJson((string) $zip->getFromName('corex-release.json'));
    $zip->close();
    for ($cursor = 0, $of = 1; $cursor < $of;) {
        ['cursor' => $cursor, 'of' => $of] = $this->staging->unpack($package, $this->manifest, $cursor);
    }

    $this->swapWith = fn (?Closure $rename = null): ReleaseSwap => new ReleaseSwap(
        $this->store,
        $this->staging,
        $this->journal,
        $this->site,
        $rename,
    );
});

afterEach(function () {
    ReleasePackages::clean();

    $remove = static function (string $path) use (&$remove): void {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            is_dir("$path/$entry") ? $remove("$path/$entry") : unlink("$path/$entry");
        }
        rmdir($path);
    };
    $remove($this->site);
});

it('puts every folder of the release in place, and leaves what is not the release\'s alone', function () {
    ($this->swapWith)()->swap($this->manifest, OWNED_BEFORE);

    // `akismet` and the uploads are as they were; `corex-legacy`, which 0.43 owned and 0.44
    // does not hold, is gone from the site; a file 0.44 no longer has went with its folder.
    expect(theSite($this->site))->toBe(theSiteAfter())
        ->and($this->journal->status())->toBe(ReleaseJournal::SWAPPED);
});

it('keeps what it replaced, whole, where going back can find it', function () {
    ($this->swapWith)()->swap($this->manifest, OWNED_BEFORE);

    $previous = $this->site . '/wp-content/corex-releases/previous';

    expect((string) file_get_contents($previous . '/wp-content/plugins/corex-core/removed-file.php'))->toBe('<?php // gone in 0.44')
        ->and((string) file_get_contents($previous . '/wp-content/plugins/corex-legacy/legacy.php'))->toBe('<?php // dropped by 0.44')
        ->and((string) file_get_contents($previous . '/wp-content/themes/corex/style.css'))->toBe('/* 0.43 */')
        // Nothing of the site that was not the release's was moved there.
        ->and(is_dir($previous . '/wp-content/plugins/akismet'))->toBeFalse()
        ->and(is_dir($previous . '/wp-content/uploads'))->toBeFalse();
});

it('moves only folders a release can own, whatever the record of the running one says', function () {
    // The record of what the running release owned is a stored value. A wrong one must not
    // be a way to have the uploads moved out of the site.
    ($this->swapWith)()->swap($this->manifest, [...OWNED_BEFORE, 'wp-content/uploads', 'wp-content', '../elsewhere']);

    expect(theSite($this->site))->toBe(theSiteAfter());
});

it('finishes a swap that was cut off, whichever rename it was cut off at', function (int $renames, bool $beforeTheRename) {
    $done    = 0;
    $cutOff  = function (string $from, string $to) use (&$done, $renames, $beforeTheRename): bool {
        if ($done === $renames && $beforeTheRename) {
            throw new RequestCutOff();
        }
        $moved = rename($from, $to);
        if (++$done === $renames && ! $beforeTheRename) {
            // Renamed, and the request died before it could say so.
            throw new RequestCutOff();
        }

        return $moved;
    };

    try {
        ($this->swapWith)($cutOff)->swap($this->manifest, OWNED_BEFORE);
    } catch (RequestCutOff) {
        // The site is now part one release and part the other.
    }

    expect($this->journal->status())->toBe(ReleaseJournal::OPEN);

    // The next request, in a new process.
    $said = ($this->swapWith)()->settle();

    expect($said)->toBe(ReleaseSwap::FINISHED)
        ->and(theSite($this->site))->toBe(theSiteAfter())
        ->and($this->journal->status())->toBe(ReleaseJournal::SWAPPED);
})->with([
    'before the first rename'            => [0, true],
    'after the first rename, unmarked'   => [1, false],
    'before the second rename'           => [1, true],
    'between a folder going and its new one coming' => [2, true],
    'half way, unmarked'                 => [4, false],
    'after the last rename, unmarked'    => [8, false],
]);

it('puts everything back when a folder cannot be moved, and says which', function () {
    $done    = 0;
    $refuses = function (string $from, string $to) use (&$done): bool {
        // The third rename: a folder the host will not let go of.
        return ++$done === 3 ? false : rename($from, $to);
    };

    try {
        ($this->swapWith)($refuses)->swap($this->manifest, OWNED_BEFORE);
    } catch (ReleaseRefused $refused) {
        expect($refused->reason)->toBe(ReleaseSwap::FAILED)
            ->and($refused->getMessage())->toContain('wp-content/');
    }

    expect(isset($refused))->toBeTrue()
        ->and(theSite($this->site))->toBe(sorted(THE_SITE_BEFORE))
        ->and($this->journal->status())->toBe(ReleaseJournal::UNDONE)
        // The release is still unpacked: the same installation can be tried again.
        ->and(is_file($this->staging->folderOf('wp-content/plugins/corex-core') . '/corex-core.php'))->toBeTrue();
});

it('does not say the site is as it was when a folder that had moved will not go back', function () {
    $done    = 0;
    $refuses = function (string $from, string $to) use (&$done): bool {
        // The third rename fails, and so does putting the second one back.
        return in_array(++$done, [3, 4], true) ? false : rename($from, $to);
    };

    try {
        ($this->swapWith)($refuses)->swap($this->manifest, OWNED_BEFORE);
    } catch (ReleaseRefused $refused) {
        // The folder that is not where it belongs is named, with where it is.
        expect($refused->reason)->toBe(ReleaseSwap::HALF_DONE)
            ->and($refused->getMessage())->not->toContain('as it was')
            ->and($refused->getMessage())->toContain('previous/wp-content/plugins/corex-core');
    }

    expect(isset($refused))->toBeTrue()
        ->and($this->journal->status())->toBe(ReleaseJournal::STUCK)
        ->and(is_dir($this->site . '/wp-content/plugins/corex-core'))->toBeFalse();

    // Going back, once the host lets go, is the same list read backwards.
    ($this->swapWith)()->undo();

    expect(theSite($this->site))->toBe(sorted(THE_SITE_BEFORE))
        ->and($this->journal->status())->toBe(ReleaseJournal::UNDONE);
});

it('has nothing to settle when no swap was cut off', function () {
    expect(($this->swapWith)()->settle())->toBeNull();

    ($this->swapWith)()->swap($this->manifest, OWNED_BEFORE);

    expect(($this->swapWith)()->settle())->toBeNull();
});

it('goes back: every folder the swap replaced is where it was', function () {
    ($this->swapWith)()->swap($this->manifest, OWNED_BEFORE);

    ($this->swapWith)()->undo();

    expect(theSite($this->site))->toBe(sorted(THE_SITE_BEFORE))
        ->and($this->journal->status())->toBe(ReleaseJournal::UNDONE);
});

it('forgets the release before the last one when another is installed', function () {
    ($this->swapWith)()->swap($this->manifest, OWNED_BEFORE);
    $previous = $this->site . '/wp-content/corex-releases/previous';

    // Unpacked again, and installed over itself: what is kept is what this one replaced.
    $package = ReleasePackages::faithful(THE_NEW_RELEASE);
    for ($cursor = 0, $of = 1; $cursor < $of;) {
        ['cursor' => $cursor, 'of' => $of] = $this->staging->unpack($package, $this->manifest, $cursor);
    }
    ($this->swapWith)()->swap($this->manifest, $this->manifest->releasePaths);

    expect((string) file_get_contents($previous . '/wp-content/plugins/corex-core/corex-core.php'))->toBe('<?php // core 0.44')
        ->and(is_dir($previous . '/wp-content/plugins/corex-legacy'))->toBeFalse();
});
