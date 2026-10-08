<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use DomainException;

/**
 * A part of a package did not start where the last one ended (spec 107, plan D12).
 *
 * Not a refusal of the package: the connection dropped, or a part was sent twice. It carries how
 * much the site holds, which is where the browser sends from next.
 */
final class ReleaseUploadOutOfStep extends DomainException
{
    public function __construct(public readonly int $received)
    {
        parent::__construct('The site holds ' . $received . ' bytes of this package.');
    }
}
