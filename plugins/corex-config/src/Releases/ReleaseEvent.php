<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * Something that happened to a site's release, as it is written down (spec 107, FR-051).
 *
 * Versions, a package's name, a reason. Never an installation's key: nothing that makes one
 * of these is given it.
 */
final readonly class ReleaseEvent
{
    public const INSTALLED = 'installed';
    public const FAILED    = 'failed';
    public const CANCELLED = 'cancelled';

    /**
     * @param string               $kind    One of this class's kinds.
     * @param string               $version The release it is about.
     * @param string|null          $from    The release the site ran before, where it knew.
     * @param array<string,scalar> $details What else is worth keeping: a package's name, a reason.
     */
    public function __construct(
        public string $kind,
        public string $version,
        public ?string $from,
        public array $details = [],
    ) {
    }

    /**
     * An event of an installation: its release, the one it replaces, its package.
     *
     * @param array<string,scalar> $details What else is worth keeping.
     */
    public static function of(string $kind, ReleaseProgress $installation, array $details = []): self
    {
        return new self(
            $kind,
            $installation->manifest()->version,
            $installation->replacedVersion(),
            ['package' => $installation->package, ...$details],
        );
    }
}
