<?php

/**
 * Integration test: the helper the suite cleans up with, on real ./wp.
 *
 * Every other file trusts `CreatedPosts` to have heard of what it wrote. It heard of no
 * attachment until 2026-10-13, because WordPress announces a new attachment by `add_attachment`
 * and returns before `wp_insert_post` fires: a file asking for attachments left one row, and one
 * file on disk, each run. Nothing said so, since a helper that hears of nothing deletes nothing
 * and fails nothing.
 *
 * @package Corex\Tests\Integration\Support
 */

declare(strict_types=1);

use Corex\Tests\Support\CreatedPosts;

/** Store a file in the uploads directory and its attachment, as a media upload does. */
function createdPostsProbeAttachment(): int
{
    $stored = wp_upload_bits('corex-created-posts-probe.txt', null, 'probe');

    return (int) wp_insert_attachment(
        ['post_title' => 'CreatedPosts probe', 'post_mime_type' => 'text/plain', 'post_status' => 'inherit'],
        $stored['file'],
    );
}

it('hears of an attachment, and deletes it with its file', function () {
    $attachments = CreatedPosts::watch('attachment');

    $attachmentId = createdPostsProbeAttachment();
    $file         = (string) get_attached_file($attachmentId);
    $stored       = is_file($file);
    $heard        = $attachments->ids();

    $attachments->delete();

    expect($stored)->toBeTrue()
        ->and($heard)->toBe([$attachmentId])
        ->and(get_post($attachmentId))->toBeNull()
        ->and(is_file($file))->toBeFalse();
});

it('hears of every type when asked for all of them, and names the ones still stored', function () {
    $posts = CreatedPosts::watchAll();

    $pageId       = (int) wp_insert_post(['post_type' => 'page', 'post_title' => 'A page that is kept']);
    $attachmentId = createdPostsProbeAttachment();
    $deletedId    = (int) wp_insert_post(['post_type' => 'post', 'post_title' => 'A post the test deletes']);
    wp_delete_post($deletedId, true);

    $stillStored = $posts->stillStored();
    $posts->delete();

    expect($stillStored)->toBe([
        '#' . $pageId . ' page "A page that is kept"',
        '#' . $attachmentId . ' attachment "CreatedPosts probe"',
    ])
        ->and($posts->stillStored())->toBe([])
        ->and(get_post($pageId))->toBeNull()
        ->and(get_post($attachmentId))->toBeNull();
});
