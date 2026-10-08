<?php

/**
 * What a real WordPress says about itself to `ReleaseHostFacts::ofThisHost()` (spec 107, FR-017).
 *
 * The unit test holds the judging to account with facts it makes up. This is the reading: the
 * four facts taken from a running WordPress, which no made-up value can stand in for.
 *
 * @package Corex\Tests\Integration\Releases
 */

declare(strict_types=1);

use Corex\Config\Releases\ReleaseHostFacts;

it('finds nothing in the way on a site that can change its own files', function () {
    $host = ReleaseHostFacts::ofThisHost([WP_CONTENT_DIR, WP_PLUGIN_DIR, get_theme_root()]);

    expect($host->fileChangesAllowed)->toBeTrue()
        ->and($host->canOpenZips)->toBeTrue()
        ->and($host->unwritable)->toBe([])
        ->and($host->blockers(0))->toBe([]);
});

it('says a site that is set not to change its files cannot install one', function () {
    add_filter('file_mod_allowed', '__return_false');

    try {
        $blockers = ReleaseHostFacts::ofThisHost([WP_CONTENT_DIR])->blockers(0);
    } finally {
        remove_filter('file_mod_allowed', '__return_false');
    }

    expect(array_column($blockers, 'reason'))->toBe([ReleaseHostFacts::FILE_CHANGES_OFF]);
});

it('does not hold a folder that is not there against the host, when it could make it', function () {
    // A site may have no must-use plugins folder. An installation makes it; what has to be
    // writable is the folder above. Asked about the missing folder itself, WordPress answered
    // "writable" on Windows and "not" on Linux, and this test was written on one and would
    // have run on the other.
    $missing = WP_CONTENT_DIR . '/corex-no-such-folder';

    expect(ReleaseHostFacts::ofThisHost([WP_CONTENT_DIR, $missing])->unwritable)->toBe([])
        ->and(file_exists($missing))->toBeFalse();
});
