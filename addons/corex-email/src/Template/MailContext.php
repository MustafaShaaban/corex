<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Template;

defined('ABSPATH') || exit;

/**
 * The typed, whitelisted data a template may read. Values are pre-assembled under
 * named roots (e.g. `event`, `site`, and named models); `get('user.name')` walks
 * dotted keys over that array only. A path outside the assembled data, or a
 * non-scalar leaf, returns the default — a template can never reach code or
 * unintended data (spec FR-003).
 */
final class MailContext
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    public function get(string $path, string $default = ''): string
    {
        $value = $this->data;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return is_scalar($value) ? (string) $value : $default;
    }
}
