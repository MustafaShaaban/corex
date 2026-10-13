<?php

/**
 * The two rules a file field is written with judge an upload and nothing else (spec 081, FR-003).
 *
 * Both passed any value that was not an array, on the reading that it was an empty field. Text is
 * not an empty field. Nothing hands these rules text for a file field any more; a form that writes
 * `mime:` on a field of another type is told so by the refusal.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Validation\Rules\MaxFileSize;
use Corex\Forms\Validation\Rules\MimeType;

it('refuses an answer that is not an upload', function (mixed $answer) {
    expect((new MimeType())->validate($answer, ['application/pdf'], []))->toBe('mime')
        ->and((new MaxFileSize())->validate($answer, ['2'], []))->toBe('max_size');
})->with([
    'a number as text' => ['224'],
    'a number' => [224],
    'a path' => ['/etc/hostname'],
]);

it('leaves an empty answer to the rule that says a field is required', function (mixed $answer) {
    expect((new MimeType())->validate($answer, ['application/pdf'], []))->toBeNull()
        ->and((new MaxFileSize())->validate($answer, ['2'], []))->toBeNull();
})->with([
    'nothing' => [null],
    'an empty string' => [''],
    'a file part with no file in it' => [['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]],
]);
