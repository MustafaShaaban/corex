<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use DomainException;

/**
 * A release package was refused, with the reason as a code a screen and a test can tell apart
 * and a message an administrator can act on (spec 107, US2).
 *
 * Nothing of a refused package is installed.
 */
final class ReleaseRefused extends DomainException
{
    public const NOT_A_PACKAGE = 'not_a_package';
    public const BUILT_BEFORE  = 'built_before';
    public const BUILT_AFTER   = 'built_after';
    public const INCOMPLETE    = 'incomplete';
    public const UNSAFE_PATH   = 'unsafe_path';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
