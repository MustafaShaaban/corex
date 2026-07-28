<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rule;

/**
 * An uploaded file is within its size cap (spec 081, FR-003).
 *
 * Written as `max_size:5` — **megabytes**, because that is the unit the person writing a form is
 * thinking in and a rule spec full of `5242880` invites the off-by-1024 that nobody notices until a
 * 5 MB file is refused for being over 5 MB.
 *
 * Measured from the file on disk, not the `size` the browser reported, for the same reason
 * {@see MimeType} reads the bytes: a declared size is a client assertion.
 */
final class MaxFileSize implements Rule
{
    private const BYTES_PER_MEGABYTE = 1024 * 1024;

    /**
     * @param array<int,string>   $params [0] cap in megabytes
     * @param array<string,mixed> $allValues
     */
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        if (! is_array($value) || ($value['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $megabytes = (float) ($params[0] ?? 0);
        if ($megabytes <= 0) {
            return null;
        }

        $path  = (string) ($value['tmp_name'] ?? '');
        $bytes = is_file($path) ? (int) filesize($path) : (int) ($value['size'] ?? 0);

        return $bytes > (int) ($megabytes * self::BYTES_PER_MEGABYTE) ? 'max_size' : null;
    }
}
