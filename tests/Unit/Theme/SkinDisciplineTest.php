<?php

/**
 * The theme is a skin: presentation only, no business logic. It must register no
 * post types/taxonomies and bootstrap no plugin — that lives in corex-core
 * (constitution Principle I, FR-010, SC-005).
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

$themeDir = dirname(__DIR__, 3) . '/theme';

it('contains no business-logic or plugin-bootstrap calls anywhere in the theme', function () use ($themeDir) {
    $forbidden = ['register_post_type', 'register_taxonomy', 'register_rest_route', 'add_shortcode'];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($themeDir, FilesystemIterator::SKIP_DOTS)
    );

    $violations = [];

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        foreach ($forbidden as $needle) {
            if (str_contains($contents, $needle)) {
                $violations[] = "{$file->getFilename()} calls {$needle}";
            }
        }
    }

    // Empty even when the theme is pure FSE (no PHP) — the skin discipline holds either way.
    expect($violations)->toBe([]);
})->skip(! is_dir(dirname(__DIR__, 3) . '/theme'), 'No theme directory present.');
