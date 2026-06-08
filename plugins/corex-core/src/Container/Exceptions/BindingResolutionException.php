<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Container\Exceptions;

defined('ABSPATH') || exit;

/**
 * Thrown when the container cannot build a requested entry (spec FR-007a, FR-009).
 */
final class BindingResolutionException extends ContainerException
{
    /**
     * The entry exists but cannot be instantiated — typically an unbound interface
     * or an abstract class with no concrete binding (FR-007a).
     */
    public static function notInstantiable(string $id): self
    {
        return new self(sprintf(
            'Cannot resolve [%s]: it is not instantiable. Bind a concrete implementation in a service provider.',
            $id
        ));
    }

    /**
     * A constructor parameter cannot be autowired (e.g. an untyped or scalar
     * parameter with no default and no explicit override) (FR-009).
     */
    public static function unresolvableParameter(string $class, string $parameter): self
    {
        return new self(sprintf(
            'Cannot resolve [%s]: unresolvable constructor parameter $%s. Provide an explicit binding or argument.',
            $class,
            $parameter
        ));
    }
}
