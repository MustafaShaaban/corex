<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

defined('ABSPATH') || exit;

/**
 * The framework release a client site is generated against (spec 102): the name people read and
 * the commit `npm run verify:framework` compares with. An empty commit means it could not be
 * resolved, which the check reports rather than guesses around.
 */
final class FrameworkBaseline
{
    public function __construct(
        public readonly string $release,
        public readonly string $commit,
    ) {
    }

    public static function unknown(string $release = ''): self
    {
        return new self($release, '');
    }

    public function isResolved(): bool
    {
        return $this->commit !== '';
    }
}
