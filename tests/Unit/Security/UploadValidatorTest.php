<?php

/**
 * Unit tests for the secure upload validator (spec 012 US1: FR-001, FR-002, SC-001/2).
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Corex\Security\Upload\UploadValidator;

function cvValidator(): UploadValidator
{
    return new UploadValidator(
        ['application/pdf' => ['pdf'], 'application/msword' => ['doc']],
        2 * 1024 * 1024, // 2 MB
    );
}

/**
 * @param array<string,mixed> $overrides
 *
 * @return array<string,mixed>
 */
function uploaded(array $overrides = []): array
{
    return array_merge([
        'name'  => 'cv.pdf',
        'type'  => 'application/pdf',
        'size'  => 100_000,
        'error' => UPLOAD_ERR_OK,
    ], $overrides);
}

it('passes a valid PDF within the size cap', function () {
    expect(cvValidator()->validate(uploaded())->valid)->toBeTrue();
});

it('rejects a disallowed type', function () {
    $result = cvValidator()->validate(uploaded(['name' => 'x.exe', 'type' => 'application/x-msdownload']));

    expect($result->valid)->toBeFalse()->and($result->reason)->toBe('type_not_allowed');
});

it('rejects an extension that does not match the type', function () {
    $result = cvValidator()->validate(uploaded(['name' => 'cv.exe']));

    expect($result->valid)->toBeFalse()->and($result->reason)->toBe('extension_mismatch');
});

it('rejects an oversized file', function () {
    $result = cvValidator()->validate(uploaded(['size' => 5 * 1024 * 1024]));

    expect($result->valid)->toBeFalse()->and($result->reason)->toBe('too_large');
});

it('rejects a PHP upload error and an empty file', function () {
    expect(cvValidator()->validate(uploaded(['error' => UPLOAD_ERR_INI_SIZE]))->reason)->toBe('upload_error')
        ->and(cvValidator()->validate(uploaded(['size' => 0]))->reason)->toBe('empty');
});
