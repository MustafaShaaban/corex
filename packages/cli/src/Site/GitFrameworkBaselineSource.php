<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

defined('ABSPATH') || exit;

/**
 * Reads the framework baseline from git (spec 102, FR-006) — the only place in this package that
 * runs a process.
 *
 * Three answers, in order: the commit the release tag points at; failing that `HEAD`, with the
 * release labelled as untagged so the record does not claim a tag that is not there; failing that
 * an empty commit, for a checkout with no repository to ask. The last is not an error here. A
 * generator that refused to generate because git was missing would be the worse failure, and
 * `npm run verify:framework` fails on the empty commit until one is recorded.
 */
final class GitFrameworkBaselineSource implements FrameworkBaselineSource
{
    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $releaseTag,
    ) {
    }

    public function current(): FrameworkBaseline
    {
        $tagged = $this->commitOf('refs/tags/' . $this->releaseTag);
        if ($tagged !== null) {
            return new FrameworkBaseline($this->releaseTag, $tagged);
        }

        $head = $this->commitOf('HEAD');
        if ($head !== null) {
            return new FrameworkBaseline($this->releaseTag . ' (untagged)', $head);
        }

        return FrameworkBaseline::unknown($this->releaseTag);
    }

    private function commitOf(string $ref): ?string
    {
        $process = proc_open(
            ['git', '-C', $this->repositoryRoot, 'rev-parse', '--quiet', '--verify', $ref . '^{commit}'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (! is_resource($process)) {
            return null;
        }

        $commit = trim((string) stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0 && preg_match('/^[0-9a-f]{40}$/', $commit) === 1 ? $commit : null;
    }
}
