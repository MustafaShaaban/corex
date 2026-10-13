<?php

/**
 * The record of a WebP file names the two files where they are.
 *
 * WordPress takes one level of slashes off a meta value on its way in, and the record was
 * handed over as it was. Where a path has a backslash in it, which is every path of a Windows
 * host, the record named two files that are not there: `C:\site\uploads\a.webp` was recorded
 * as `C:siteuploadsa.webp`, and `wp corex media reset-webp` deletes the file the record names
 * (measured 2026-10-13, DECISIONS #332).
 *
 * On real WordPress, through a real upload into a folder whose path has a backslash in it. On
 * Windows that is a folder inside a folder, and elsewhere a folder with a backslash in its
 * name: either way the files are there, and the record has to find them.
 *
 * @package Corex\Tests\Integration\Media
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Media\WebpMeta;
use Corex\Media\WebpResetCommand;

beforeEach(function () {
    if (! function_exists('imagewebp')) {
        $this->markTestSkipped('GD WebP support is required for the conversion path.');
    }

    Boot::app();
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $this->webpEnabledBefore = get_option('corex_media_webp_enabled');
    update_option('corex_media_webp_enabled', 1);

    $uploads = wp_upload_dir();
    $this->folder = $uploads['basedir'] . '/corex-kept-' . bin2hex(random_bytes(4));
    $inside = $this->folder . '/with\\backslash';
    $this->uploadsThere = static fn (array $dir): array => ['path' => $inside, 'basedir' => $inside, 'subdir' => ''] + $dir;
    add_filter('upload_dir', $this->uploadsThere);
    wp_mkdir_p($inside);

    $this->original = $inside . '/palette-with-alpha.png';
    copy(dirname(__DIR__, 2) . '/Fixtures/Media/palette-with-alpha.png', $this->original);
    $this->attachmentId = (int) wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title' => 'A picture in a folder with a backslash',
        'post_status' => 'inherit',
    ], $this->original);
});

afterEach(function () {
    if (! isset($this->folder)) {
        return;
    }
    wp_delete_attachment($this->attachmentId, true);
    remove_filter('upload_dir', $this->uploadsThere);

    $inside = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->folder, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($inside as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($this->folder);

    // Restored rather than deleted: this is a real site setting.
    $this->webpEnabledBefore === false
        ? delete_option('corex_media_webp_enabled')
        : update_option('corex_media_webp_enabled', $this->webpEnabledBefore);
});

it('records the picture and its WebP under the paths they are at', function () {
    wp_generate_attachment_metadata($this->attachmentId, $this->original);
    $record = get_post_meta($this->attachmentId, WebpMeta::META_KEY, true);

    expect($record)->toBeArray()
        ->and($record['original_path'])->toBe(get_attached_file($this->attachmentId))
        ->and(is_file($record['original_path']))->toBeTrue()
        ->and(is_file($record['generated_path']))->toBeTrue()
        // The file `wp corex media reset-webp` deletes for this picture.
        ->and(is_file((string) WebpResetCommand::target($record)))->toBeTrue()
        // What is not text in the record is read where it is a number or a switch.
        ->and($record['original_bytes'])->toBe(filesize($this->original))
        ->and($record['quality'])->toBeInt()
        ->and($record['active_for_delivery'])->toBeBool();
});
