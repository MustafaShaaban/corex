<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Support;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Thrown when a requested artifact name is not a valid class identifier (FR-009).
 */
final class InvalidNameException extends InvalidArgumentException
{
}
