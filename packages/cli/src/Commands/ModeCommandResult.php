<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

/**
 * What `wp corex mode` has to say, without WP-CLI: whether it succeeded, the mode the site is in
 * afterwards, and the sentence to print. Tests read this; {@see ModeCommand::run()} prints it.
 */
final readonly class ModeCommandResult
{
    public function __construct(
        public bool $ok,
        public string $mode,
        public string $message,
    ) {
    }

    /**
     * A deploy script reads the exit code, not the sentence: anything that left the site in a
     * mode other than the one asked for is a failure.
     */
    public function shouldExitNonZero(): bool
    {
        return ! $this->ok;
    }
}
