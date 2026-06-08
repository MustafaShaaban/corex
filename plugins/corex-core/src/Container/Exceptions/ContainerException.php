<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Container\Exceptions;

defined('ABSPATH') || exit;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * Base for all container resolution errors (PSR-11 ContainerExceptionInterface).
 */
class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}
