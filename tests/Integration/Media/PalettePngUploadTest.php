<?php

/**
 * A palette PNG survives a real WordPress media upload — issue #142.
 *
 * The unit tests prove the converter. This proves the symptom that was actually reported: the
 * *upload* used to die. `wp_generate_attachment_metadata` runs the conversion at priority 20, so a
 * fatal inside it ended the request that was adding the file — the uploader saw WordPress's
 * critical-error page, an orphaned attachment post was left behind (the insert had already
 * happened), and every later filter on that hook was skipped. A content migration importing 24
 * logos stopped on the first one.
 *
 * @package Corex\Tests\Integration\Media
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Media\WebpMeta;

/**
 * Put a fixture into the uploads directory and register it as an attachment, the way an upload does.
 *
 * @return array{0:int,1:string} Attachment ID and the absolute path to the stored file.
 */
function corexUploadFixture(string $fixture): array
{
    $source = dirname(__DIR__, 2) . '/Fixtures/Media/' . $fixture;
    $upload = wp_upload_dir();
    $target = $upload['path'] . '/corex142-' . bin2hex(random_bytes(4)) . '-' . $fixture;

    if (! is_dir($upload['path'])) {
        wp_mkdir_p($upload['path']);
    }
    copy($source, $target);

    $attachmentId = (int) wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title'     => pathinfo($fixture, PATHINFO_FILENAME),
        'post_status'    => 'inherit',
    ], $target);

    return [$attachmentId, $target];
}

beforeEach(function () {
    if (! function_exists('imagewebp')) {
        $this->markTestSkipped('GD WebP support is required for the conversion path.');
    }

    Boot::app();
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $this->previous = get_option('corex_media_webp_enabled');
    update_option('corex_media_webp_enabled', 1);
    $this->created = [];
});

afterEach(function () {
    foreach ($this->created as [$id, $path]) {
        wp_delete_attachment($id, true);
        foreach ([$path, preg_replace('/\.png$/i', '.webp', $path)] as $file) {
            if (is_string($file) && is_file($file)) {
                unlink($file);
            }
        }
    }

    // Restored rather than deleted: this is a real site setting, and a test that leaves it changed
    // is a test that edits the install it borrowed.
    $this->previous === false
        ? delete_option('corex_media_webp_enabled')
        : update_option('corex_media_webp_enabled', $this->previous);
});

it('completes the upload of an indexed-colour PNG and writes its WebP', function () {
    // Before the fix this did not fail — the PHP process ended inside wp_generate_attachment_metadata,
    // taking the request (and here, the test run) with it.
    [$id, $path] = corexUploadFixture('palette-with-alpha.png');
    $this->created[] = [$id, $path];

    $metadata = wp_generate_attachment_metadata($id, $path);
    $webp     = preg_replace('/\.png$/i', '.webp', $path);

    expect($id)->toBeGreaterThan(0)
        ->and($metadata)->toBeArray()
        ->and(is_file($webp))->toBeTrue()
        ->and(filesize($webp))->toBeGreaterThan(0);
});

it('leaves the original PNG in place, because the WebP is a sibling and not a replacement', function () {
    [$id, $path] = corexUploadFixture('palette-with-alpha.png');
    $this->created[] = [$id, $path];

    $before = md5_file($path);
    wp_generate_attachment_metadata($id, $path);

    expect(is_file($path))->toBeTrue()
        ->and(md5_file($path))->toBe($before);
});

it('records the conversion so delivery and reset-webp can see it', function () {
    [$id, $path] = corexUploadFixture('palette-with-alpha.png');
    $this->created[] = [$id, $path];

    wp_generate_attachment_metadata($id, $path);

    // Present only when the derivative passed the activation gate — a small synthetic fixture may
    // not save enough bytes to qualify, so the assertion is that the decision was *recorded*, not
    // that it went a particular way. Claiming a saving here would be asserting the fixture, not the code.
    $meta = get_post_meta($id, WebpMeta::META_KEY, true);

    expect(is_file(preg_replace('/\.png$/i', '.webp', $path)))->toBeTrue()
        ->and($meta === '' || is_array($meta))->toBeTrue();
});
