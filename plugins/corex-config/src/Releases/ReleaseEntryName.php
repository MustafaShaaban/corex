<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * The name of a file inside a package, which is also where it is written when it is unpacked
 * (spec 107, plan D3).
 *
 * One rule, asked by the inspection that refuses a package and again by the unpacking that
 * writes it.
 */
final class ReleaseEntryName
{
    /**
     * An entry is written where its name says. One that climbs with `..`, starts at the root
     * or names a drive would be written outside the folder the package is unpacked to.
     */
    public static function leavesThePackage(string $name): bool
    {
        return str_starts_with($name, '/')
            || str_contains($name, '\\')
            || str_contains($name, "\0")
            || preg_match('#^[A-Za-z]:#', $name) === 1
            || in_array('..', explode('/', $name), true);
    }
}
