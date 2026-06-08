<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Container\Exceptions;

defined('ABSPATH') || exit;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when no entry exists for an id and it is not an existing class (PSR-11 NotFound).
 */
final class EntryNotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    public static function forId(string $id): self
    {
        return new self(sprintf('No container entry found for [%s].', $id));
    }
}
