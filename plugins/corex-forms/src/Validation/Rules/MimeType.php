<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rule;

/**
 * An uploaded file is one of the types this field accepts (spec 081, FR-003).
 *
 * Reads the file's **content**, through WordPress's own `wp_check_filetype_and_ext()`, not the
 * `type` the browser declared — that is set by the client and is worth exactly as much as any other
 * client assertion. A `.pdf` that is really a `.php` is caught here, before anything is stored.
 *
 * Written as `mime:application/pdf,image/png`. Empty is deferred to `required`, as every rule here
 * does, so an optional upload stays possible.
 */
final class MimeType implements Rule
{
    /**
     * @param array<int,string>   $params allowed mime types
     * @param array<string,mixed> $allValues
     */
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        if (! is_array($value) || ($value['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $allowed = array_values(array_filter(array_map('trim', $params)));
        if ($allowed === []) {
            return null;
        }

        $checked = wp_check_filetype_and_ext(
            (string) ($value['tmp_name'] ?? ''),
            (string) ($value['name'] ?? ''),
        );

        $actual = (string) ($checked['type'] ?? '');

        // An empty result means WordPress could not agree the name and the bytes describe the same
        // thing. That is a refusal, not an unknown: the alternative is storing a file whose type
        // nothing has established.
        return $actual !== '' && in_array($actual, $allowed, true) ? null : 'mime';
    }
}
