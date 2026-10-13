<?php

/**
 * Integration test: the attachment store keeps only a file the server received as an upload, on
 * real ./wp (spec 081, FR-004).
 *
 * `wp_handle_upload()` refuses a path PHP did not receive in this request. The store read the file
 * at that path before asking: its answer said whether a file was there and what it held. It now
 * asks first.
 *
 * A file WordPress accepts as an upload cannot be made from here. `submission-file.spec.js` sends
 * a real one from a browser, through this store.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Security\Upload\AttachmentStore;
use Corex\Security\Upload\UploadValidator;
use Corex\Tests\Support\CreatedPosts;

beforeEach(function () {
    $this->attachments = CreatedPosts::watch('attachment');
    $this->path        = (string) tempnam(get_temp_dir(), 'corex-unsent');
});

afterEach(function () {
    $this->attachments->delete();
    if (is_file($this->path)) {
        wp_delete_file($this->path);
    }
});

it('refuses a file that is on the server and was not uploaded, whatever it holds', function (string $bytes) {
    file_put_contents($this->path, $bytes);

    $result = (new AttachmentStore(new UploadValidator(['application/pdf' => ['pdf']], 1_000_000)))->store([
        'name' => 'cv.pdf',
        'type' => 'application/pdf',
        'tmp_name' => $this->path,
        'error' => UPLOAD_ERR_OK,
        'size' => strlen($bytes),
    ], 'form-cv');

    expect($result->stored)->toBeFalse()
        ->and($result->reason)->toBe('not_uploaded')
        ->and($this->attachments->ids())->toBe([])
        // Left where it was, with what it held.
        ->and(file_get_contents($this->path))->toBe($bytes);
})->with([
    'a PDF' => ["%PDF-1.4\n%%EOF\n"],
    'text named as a PDF' => ["DB_PASSWORD=not-a-pdf\n"],
]);
