<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

/**
 * Scaffolds a user guide — a `Guide` of topics and numbered steps the developer registers with the
 * `GuideRegistry` (spec 084), so a site's own guides appear beside Corex's on the Guides screen.
 *
 * The scaffold deliberately includes a warning step and a `result` on every instruction. Both are
 * the parts an author skips when starting from a blank file, and both are what make a guide
 * checkable rather than merely readable.
 */
final class GuideGenerator extends Generator
{
    public function stub(): string
    {
        return 'guide';
    }

    public function suffix(): string
    {
        return 'Guide';
    }

    public function subPath(): string
    {
        return 'Guides';
    }

    /**
     * @return array<string,string>
     */
    public function placeholders(string $className, GeneratorContext $context): array
    {
        // `$className` already carries the suffix — GeneratorEngine applies it before calling this,
        // so appending it here again produced `ProjectsGuideGuide`. The id and the human title both
        // want the bare name, so it comes off once.
        $bare = preg_replace('/Guide$/', '', $className) ?: $className;

        // CamelCase → kebab-case id (Projects → projects, JobApplications → job-applications),
        // matching how a guide id reads as a URL fragment on the Guides screen.
        $id = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $bare));

        return [
            'class'     => $className,
            'namespace' => $context->namespace . '\\Guides',
            'id'        => $id,
            'title'     => $bare,
        ];
    }
}
