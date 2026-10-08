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

    /* What a package is refused for once it is a file on the site (spec 107, plan D3). */
    public const NOT_A_ZIP       = 'not_a_zip';
    public const OTHER_CLIENT    = 'other_client';
    public const NEEDS_PHP       = 'needs_php';
    public const NEEDS_WORDPRESS = 'needs_wordpress';
    public const MISSING_FOLDER  = 'missing_folder';
    public const UNSAFE_ENTRY    = 'unsafe_entry';
    public const FORBIDDEN_ENTRY = 'forbidden_entry';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
