<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * Thrown when a stub still contains a `{{ … }}` placeholder after rendering (FR-003).
 */
final class UnresolvedPlaceholderException extends RuntimeException
{
}
