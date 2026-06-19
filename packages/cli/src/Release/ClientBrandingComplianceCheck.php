<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

defined('ABSPATH') || exit;

/**
 * Applies the client-branding boundary for generated sites.
 */
final class ClientBrandingComplianceCheck
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_PREFIXES = [
        'plugins/corex-',
        'addons/corex-',
        'packages/',
        'theme/',
        'themes/corex/',
    ];

    /**
     * @param list<string> $changedFiles
     *
     * @return array{passed:bool,violations:list<string>}
     */
    public function evaluate(array $changedFiles, bool $allowFramework = false): array
    {
        return (new ComplianceCheck())->evaluate($changedFiles, self::FORBIDDEN_PREFIXES, $allowFramework);
    }
}

