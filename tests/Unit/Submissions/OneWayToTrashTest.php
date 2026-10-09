<?php

/**
 * Nothing in CoreX trashes a post the WordPress way (spec 105, US4; FR-023).
 *
 * `wp_trash_post()` puts WordPress's clock on what it trashes, writes nothing anywhere, asks nobody
 * who may, and deletes on the spot on a site that sets `EMPTY_TRASH_DAYS` to 0. The retention panel
 * and the Data screen each trashed a submission with it, beside the inbox's own trash, until this
 * slice. A third caller would be the same defect again, so the source is read for one.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('calls wp_trash_post() nowhere', function () {
    $callers = [];
    $root = str_replace('\\', '/', ThemeContract::root()) . '/';

    foreach (['plugins', 'addons', 'packages'] as $relativeRoot) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . $relativeRoot, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($file->getExtension() !== 'php' || str_contains($path, '/vendor/') || str_contains($path, '/node_modules/')) {
                continue;
            }

            // Cheap first: only a file that names the function is read without its comments.
            if (! str_contains((string) file_get_contents($path), 'wp_trash_post')) {
                continue;
            }
            if (preg_match('/\bwp_trash_post\s*\(/', php_strip_whitespace($path)) === 1) {
                $callers[] = str_replace($root, '', $path);
            }
        }
    }

    expect($callers)->toBe([]);
});
