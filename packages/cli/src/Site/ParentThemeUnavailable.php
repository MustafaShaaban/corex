<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * The CoreX theme's `theme.json` could not be read or decoded at any place it was looked for
 * (spec 135, FR-012). A client theme is a child of the CoreX theme and lists its tokens from that
 * file, so make:site stops before writing anything. The paths are kept for the command to print.
 */
final class ParentThemeUnavailable extends RuntimeException
{
    /**
     * @param list<string> $paths Every path looked in, in order.
     */
    public function __construct(public readonly array $paths)
    {
        parent::__construct(sprintf(
            'The CoreX theme\'s theme.json could not be read at any of: %s. A client theme is a child of '
            . 'the CoreX theme and lists its tokens from that file, so nothing was generated.',
            implode(', ', $paths),
        ));
    }
}
