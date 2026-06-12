<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

use Corex\Cli\Release\VersionPlan;

/**
 * `wp corex version <semver> [--dry-run]` — stamps a release version across every framework plugin
 * /theme header + `COREX_*_VERSION` constant in one step, so the headers never drift from the
 * release tag. The pure {@see VersionPlan} computes the edits; this thin command reads the files,
 * applies the plan (or previews it with `--dry-run`), and writes only what changed.
 */
final class VersionCommand
{
    /**
     * @param list<string> $files absolute paths of the version-bearing framework files
     */
    public function __construct(
        private readonly VersionPlan $plan,
        private readonly array $files,
    ) {
    }

    /**
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public function run(array $args, array $assoc): void
    {
        $version = $args[0] ?? '';

        if (! VersionPlan::isValid($version)) {
            \WP_CLI::error(sprintf(__('Invalid version "%s" — expected semver x.y.z.', 'corex'), $version));

            return;
        }

        $dryRun   = isset($assoc['dry-run']);
        $contents = [];

        foreach ($this->files as $path) {
            if (is_readable($path)) {
                $contents[$path] = (string) file_get_contents($path);
            }
        }

        $changed = $this->plan->plan($version, $contents);

        if ($changed === []) {
            \WP_CLI::success(sprintf(__('Already aligned to %s — nothing to change.', 'corex'), $version));

            return;
        }

        foreach ($changed as $path => $next) {
            if ($dryRun) {
                \WP_CLI::line(sprintf('would update: %s', $path));

                continue;
            }

            file_put_contents($path, $next);
            \WP_CLI::line(sprintf('updated: %s', $path));
        }

        if ($dryRun) {
            \WP_CLI::success(sprintf(__('Dry run: %d file(s) would be stamped to %s.', 'corex'), count($changed), $version));

            return;
        }

        \WP_CLI::success(sprintf(__('Stamped %d file(s) to %s.', 'corex'), count($changed), $version));
    }
}
