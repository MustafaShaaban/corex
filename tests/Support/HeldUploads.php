<?php

/**
 * The files a site holds as form uploads, for a test that needs no WordPress.
 *
 *     new HeldUploads(['cv' => [224 => ['name' => 'salma-cv.pdf', 'missing' => false]]]);
 *
 * By field first, as the real one answers: a file uploaded through `cv` is not a file of any
 * other field, whatever its number.
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Config\Submissions\SubmissionFiles;

final class HeldUploads implements SubmissionFiles
{
    /** @param array<string,array<int,array{name:string,missing:bool}>> $stored */
    public function __construct(private readonly array $stored)
    {
    }

    public function uploaded(string $field, int $attachmentId): ?array
    {
        $file = $this->stored[$field][$attachmentId] ?? null;

        return $file === null ? null : ['id' => $attachmentId] + $file;
    }

    public function address(int $submissionId, string $field): string
    {
        return sprintf('https://example.test/file?submission=%d&field=%s', $submissionId, $field);
    }
}
